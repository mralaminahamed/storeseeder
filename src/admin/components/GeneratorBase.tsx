import { ChevronDown } from "lucide-react";
import { motion, AnimatePresence, Variants } from "framer-motion";
import { Button } from "./ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "./ui/card";
import { Input } from "./ui/input";
import { Label } from "./ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";
import { Switch } from "./ui/switch";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "./ui/collapsible";

import { useState, RawHTML } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

// TypeScript interfaces
interface ParameterConfig {
  type: string;
  title?: string;
  description?: string;
  default?: any;
  enum?: string[];
  minimum?: number;
  maximum?: number;
  items?: {
    type?: string;
    enum?: string[];
  };
  properties?: Record<string, ParameterConfig>;
  dependsOn?: Record<string, any>;
}

interface GeneratorResult {
  message: string;
  generated?: number;
  [key: string]: any;
}

interface GeneratorBaseProps {
  title: string;
  description: string;
  apiEndpoint: string;
  parameterConfig: Record<string, ParameterConfig>;
}

export default function GeneratorBase({
  title,
  description,
  apiEndpoint,
  parameterConfig,
}: GeneratorBaseProps) {
  const [isLoading, setIsLoading] = useState(false);
  const [result, setResult] = useState<GeneratorResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [formData, setFormData] = useState<Record<string, any>>(() => {
    const initialData: Record<string, any> = {};

    // Initialize with defaults from parameterConfig
    const initializeDefaults = (config: Record<string, ParameterConfig>, prefix = "") => {
      Object.entries(config).forEach(([key, paramConfig]) => {
        const fullKey = prefix ? `${prefix}.${key}` : key;

        if (paramConfig.default !== undefined) {
          initialData[fullKey] = paramConfig.default;
        } else if (paramConfig.type === "object" && paramConfig.properties) {
          initialData[fullKey] = {};
          initializeDefaults(paramConfig.properties, fullKey);
        } else if (paramConfig.type === "array") {
          initialData[fullKey] = paramConfig.default || [];
        } else if (paramConfig.type === "boolean") {
          initialData[fullKey] = paramConfig.default || false;
        } else if (paramConfig.type === "number") {
          initialData[fullKey] = paramConfig.default || 0;
        } else if (paramConfig.type === "string") {
          initialData[fullKey] = paramConfig.default || "";
        }
      });
    };

    initializeDefaults(parameterConfig);
    return initialData;
  });

  const handleInputChange = (key: string, value: any) => {
    setFormData((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  const handleGenerate = async () => {
    setIsLoading(true);
    setError(null);
    setResult(null);

    try {
      const data = await apiFetch({
        path: apiEndpoint,
        method: "POST",
        data: formData,
      });

      setResult(data as GeneratorResult);
    } catch (err) {
      const errorMessage =
        err instanceof Error
          ? err.message
          : __(
              "An error occurred while generating data.",
              "fluent-cart-fakerpress",
            );
      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  // Check if a parameter should be shown based on dependencies
  const shouldShowParameter = (key: string, config: ParameterConfig): boolean => {
    if (!config.dependsOn) return true;

    return Object.entries(config.dependsOn).every(([depKey, depValue]) => {
      const currentValue = formData[depKey];
      return Array.isArray(depValue) ? depValue.includes(currentValue) : currentValue === depValue;
    });
  };

  // Animation variants
  const containerVariants: Variants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
      },
    },
  };

  const itemVariants: Variants = {
    hidden: { opacity: 0, y: 20 },
    visible: {
      opacity: 1,
      y: 0,
      transition: {
        duration: 0.4,
        ease: [0.4, 0.0, 0.2, 1],
      },
    },
  };

  const renderParameterInput = (key: string, config: ParameterConfig) => {
    if (!shouldShowParameter(key, config)) return null;

    const value = formData[key];

    switch (config.type) {
      case "string":
        if (config.enum) {
          return (
            <motion.div key={key} variants={itemVariants} className="space-y-2">
              <Label htmlFor={key}>
                {config.title || config.description}
                {config.description && config.title && (
                  <span className="block text-sm text-gray-500 font-normal mt-1">
                    {config.description}
                  </span>
                )}
              </Label>
              <Select
                value={value || ""}
                onValueChange={(newValue) => handleInputChange(key, newValue)}
              >
                <SelectTrigger>
                  <SelectValue placeholder={__("Select an option", "fluent-cart-fakerpress")} />
                </SelectTrigger>
                <SelectContent>
                  {config.enum.map((option) => (
                    <SelectItem key={option} value={option}>
                      {option}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </motion.div>
          );
        }
        return (
          <motion.div key={key} variants={itemVariants} className="space-y-2">
            <Label htmlFor={key}>
              {config.title || config.description}
              {config.description && config.title && (
                <span className="block text-sm text-gray-500 font-normal mt-1">
                  {config.description}
                </span>
              )}
            </Label>
            <Input
              id={key}
              type="text"
              value={value || ""}
              onChange={(e) => handleInputChange(key, e.target.value)}
              placeholder={config.default || ""}
            />
          </motion.div>
        );

      case "number":
        return (
          <motion.div key={key} variants={itemVariants} className="space-y-2">
            <Label htmlFor={key}>
              {config.title || config.description}
              {config.description && config.title && (
                <span className="block text-sm text-gray-500 font-normal mt-1">
                  {config.description}
                </span>
              )}
            </Label>
            <Input
              id={key}
              type="number"
              value={value || ""}
              onChange={(e) => handleInputChange(key, parseFloat(e.target.value) || 0)}
              min={config.minimum}
              max={config.maximum}
              placeholder={config.default?.toString() || ""}
            />
          </motion.div>
        );

      case "boolean":
        return (
          <motion.div key={key} variants={itemVariants} className="flex items-center justify-between space-x-2">
            <div className="flex-1">
              <Label htmlFor={key} className="text-sm font-medium">
                {config.title || config.description}
                {config.description && config.title && (
                  <span className="block text-xs text-gray-500 font-normal mt-1">
                    {config.description}
                  </span>
                )}
              </Label>
            </div>
            <Switch
              id={key}
              checked={value || false}
              onCheckedChange={(checked) => handleInputChange(key, checked)}
            />
          </motion.div>
        );

      case "array":
        if (config.items?.enum) {
          // Multi-select for enum arrays
          const currentValues = Array.isArray(value) ? value : (config.default as string[] || []);
          return (
            <motion.div key={key} variants={itemVariants} className="space-y-3">
              <Label className="text-sm font-medium">
                {config.title || config.description}
                {config.description && config.title && (
                  <span className="block text-xs text-gray-500 font-normal mt-1">
                    {config.description}
                  </span>
                )}
              </Label>
              <div className="space-y-2 pl-4 border-l-2 border-gray-100">
                {config.items.enum.map((option: string) => (
                  <div key={option} className="flex items-center space-x-2">
                    <input
                      id={`${key}-${option}`}
                      type="checkbox"
                      checked={currentValues.includes(option)}
                      onChange={(e) => {
                        const newValues = e.target.checked
                          ? [...currentValues, option]
                          : currentValues.filter(v => v !== option);
                        handleInputChange(key, newValues);
                      }}
                      className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <Label htmlFor={`${key}-${option}`} className="text-sm">
                      {option}
                    </Label>
                  </div>
                ))}
              </div>
            </motion.div>
          );
        }
        return (
          <motion.div key={key} variants={itemVariants} className="space-y-2">
            <Label htmlFor={key}>
              {config.title || config.description}
              {config.description && config.title && (
                <span className="block text-sm text-gray-500 font-normal mt-1">
                  {config.description}
                </span>
              )}
            </Label>
            <Input
              id={key}
              type="text"
              value={Array.isArray(value) ? value.join(", ") : (value || "")}
              onChange={(e) => handleInputChange(key, e.target.value.split(",").map(s => s.trim()).filter(Boolean))}
              placeholder={Array.isArray(config.default) ? config.default.join(", ") : ""}
            />
          </motion.div>
        );

      case "object":
        if (config.properties) {
          return (
            <motion.div key={key} variants={itemVariants} className="space-y-4">
              <Collapsible defaultOpen={true}>
                <CollapsibleTrigger className="flex items-center justify-between w-full p-3 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                  <div>
                    <h4 className="text-sm font-medium text-left">
                      {config.title || config.description}
                      {config.description && config.title && (
                        <span className="block text-xs text-gray-500 font-normal mt-1">
                          {config.description}
                        </span>
                      )}
                    </h4>
                  </div>
                  <ChevronDown className="h-4 w-4 text-gray-500 transition-transform duration-200 data-[state=open]:rotate-180" />
                </CollapsibleTrigger>
                <CollapsibleContent className="space-y-4 pt-4 pl-4 border-l-2 border-gray-200">
                  <motion.div
                    variants={containerVariants}
                    initial="hidden"
                    animate="visible"
                    className="grid grid-cols-1 md:grid-cols-2 gap-4"
                  >
                    {Object.entries(config.properties).map(([propKey, propConfig]) =>
                      renderParameterInput(`${key}.${propKey}`, propConfig)
                    )}
                  </motion.div>
                </CollapsibleContent>
              </Collapsible>
            </motion.div>
          );
        }
        return null;

      default:
        return null;
    }
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6 }}
    >
      <Card className="shadow-lg border-0 bg-gradient-to-br from-white to-gray-50/50">
        <CardHeader className="pb-6">
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.2, duration: 0.5 }}
          >
            <CardTitle className="text-2xl font-bold text-gray-900 flex items-center">
              <div className="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                <span className="text-white font-bold text-sm">G</span>
              </div>
              {title}
            </CardTitle>
            <CardDescription className="text-gray-600 mt-2 leading-relaxed">
              {description}
            </CardDescription>
          </motion.div>
        </CardHeader>
        <CardContent className="space-y-8">
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="grid gap-6"
          >
            {Object.entries(parameterConfig).map(([key, config]) =>
              renderParameterInput(key, config)
            )}
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4, duration: 0.5 }}
            className="pt-6 border-t border-gray-100"
          >
            <Button
              onClick={handleGenerate}
              disabled={isLoading}
              className="w-full h-12 text-base font-semibold bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 shadow-lg hover:shadow-xl transition-all duration-200"
            >
              {isLoading ? (
                <motion.div
                  animate={{ rotate: 360 }}
                  transition={{ duration: 1, repeat: Infinity, ease: "linear" }}
                  className="w-5 h-5 border-2 border-white border-t-transparent rounded-full mr-2"
                />
              ) : null}
              {isLoading
                ? __("Generating...", "fluent-cart-fakerpress")
                : sprintf(__("Generate %s", "fluent-cart-fakerpress"), title.toLowerCase())}
            </Button>
          </motion.div>

          <AnimatePresence>
            {error && (
              <motion.div
                initial={{ opacity: 0, scale: 0.95, y: 10 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.95, y: -10 }}
                transition={{ duration: 0.3 }}
                className="rounded-lg bg-red-50 border border-red-200 p-4"
              >
                <div className="flex items-start">
                  <div className="flex-shrink-0">
                    <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                    </svg>
                  </div>
                  <div className="ml-3">
                    <h3 className="text-sm font-medium text-red-800">
                      {__("Generation Failed", "fluent-cart-fakerpress")}
                    </h3>
                    <div className="mt-2 text-sm text-red-700">
                      <RawHTML>{error}</RawHTML>
                    </div>
                  </div>
                </div>
              </motion.div>
            )}
          </AnimatePresence>

          <AnimatePresence>
            {result && (
              <motion.div
                initial={{ opacity: 0, scale: 0.95, y: 10 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.95, y: -10 }}
                transition={{ duration: 0.3 }}
                className="rounded-lg bg-green-50 border border-green-200 p-4"
              >
                <div className="flex items-start">
                  <div className="flex-shrink-0">
                    <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                    </svg>
                  </div>
                  <div className="ml-3">
                    <h3 className="text-sm font-medium text-green-800">
                      {__("Generation Successful", "fluent-cart-fakerpress")}
                    </h3>
                    <div className="mt-2 text-sm text-green-700">
                      <RawHTML>{result.message}</RawHTML>
                    </div>
                    {result.generated && (
                      <div className="mt-3 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                          <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                        </svg>
                        {sprintf(
                          __("Generated %d items", "fluent-cart-fakerpress"),
                          result.generated
                        )}
                      </div>
                    )}
                  </div>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </CardContent>
      </Card>
    </motion.div>
  );
}