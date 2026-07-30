import React from "react";

interface TextFieldProps {
  value: string;
  onChange: (v: string) => void;
  ph?: string;
  /** Lets a caller bind its own <label htmlFor> to this input. */
  id?: string;
}

export function TextField({ value, onChange, ph, id }: TextFieldProps) {
  return (
    <input
      id={id}
      className="fp-input fp-focusable"
      value={value}
      placeholder={ph}
      onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
        onChange(e.target.value)
      }
    />
  );
}
