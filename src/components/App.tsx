import React from 'react';
import { createHashRouter, RouterProvider } from 'react-router-dom';

import { ThemeProvider } from '@/theme/ThemeProvider';
import { StatsProvider } from '@/providers/StatsProvider';
import { ToastProvider } from '@/providers/ToastProvider';
import { BatchProvider } from '@/providers/BatchProvider';
import GeneratorPage from '@/components/Pages/GeneratorPage';
import HomePage from '@/components/Pages/HomePage';
import PluginsPage from '@/components/Pages/PluginsPage';
import RootLayout from '@/components/Pages/RootLayout';
import SettingsPage from '@/components/Pages/SettingsPage';

const router = createHashRouter([
  {
    path: '/',
    element: <RootLayout />,
    children: [
      { index: true,              element: <HomePage />      },
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
          <BatchProvider>
            <RouterProvider router={router} />
          </BatchProvider>
        </StatsProvider>
      </ToastProvider>
    </ThemeProvider>
  );
}
