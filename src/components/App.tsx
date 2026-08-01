import React from 'react';
import { createHashRouter, RouterProvider } from 'react-router-dom';

import { ThemeProvider } from '@/theme/ThemeProvider';
import { StatsProvider } from '@/providers/StatsProvider';
import { ToastProvider } from '@/providers/ToastProvider';
import { BatchProvider } from '@/providers/BatchProvider';
import { PlatformProvider } from '@/providers/PlatformProvider';
import GeneratorPage from '@/components/Pages/GeneratorPage';
import HomePage from '@/components/Pages/HomePage';
import PluginsPage from '@/components/Pages/PluginsPage';
import RecipesLayout from '@/components/Pages/RecipesLayout';
import RecipeDoneView from '@/components/recipes/RecipeDoneView';
import RecipePicker from '@/components/recipes/RecipePicker';
import RecipeRunView from '@/components/recipes/RecipeRunView';
import RootLayout from '@/components/Pages/RootLayout';
import SettingsPage from '@/components/Pages/SettingsPage';

const router = createHashRouter([
  {
    path: '/',
    element: <RootLayout />,
    children: [
      { index: true,              element: <HomePage />      },
      {
        // Three screens, not three values of a `stage` variable: a build worth linking to, a Back
        // button that returns to the list rather than out of the plugin, and a refresh that lands
        // somewhere honest. The layout holds the run, so navigating between them does not cancel it.
        path: 'recipes',
        element: <RecipesLayout />,
        children: [
          { index: true,          element: <RecipePicker />  },
          { path: ':id/run',      element: <RecipeRunView /> },
          { path: ':id/done',     element: <RecipeDoneView /> },
        ],
      },
      { path: 'generator/:type',  element: <GeneratorPage /> },
      { path: 'settings',         element: <SettingsPage />  },
      { path: 'plugins',          element: <PluginsPage />   },
    ],
  },
]);

export default function App() {
  return (
    <ThemeProvider>
      <ToastProvider>
        <StatsProvider>
          {/* Outside BatchProvider: queued runs read the target when they run. */}
          <PlatformProvider>
            <BatchProvider>
              <RouterProvider router={router} />
            </BatchProvider>
          </PlatformProvider>
        </StatsProvider>
      </ToastProvider>
    </ThemeProvider>
  );
}
