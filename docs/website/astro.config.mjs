// @ts-check
import { defineConfig } from 'astro/config'
import starlight from '@astrojs/starlight'
import mermaid from 'astro-mermaid'

export default defineConfig({
  site: 'https://mralaminahamed.github.io',
  base: '/storeseeder',
  integrations: [
    mermaid({
      theme: 'neutral',
      autoTheme: true,
    }),
    starlight({
      title: 'StoreSeeder',
      tagline: 'Realistic test data for WordPress e-commerce — a whole shop in one click.',
      favicon: '/favicon.svg',
      logo: { src: './src/assets/logo.svg', alt: 'StoreSeeder' },
      social: [
        { icon: 'external', label: 'WordPress.org', href: 'https://wordpress.org/plugins/storeseeder/' },
        { icon: 'github', label: 'GitHub', href: 'https://github.com/mralaminahamed/storeseeder' },
      ],
      editLink: {
        baseUrl: 'https://github.com/mralaminahamed/storeseeder/edit/trunk/docs/website/',
      },
      lastUpdated: true,
      pagination: true,
      expressiveCode: {
        themes: ['github-dark', 'github-light'],
        styleOverrides: {
          borderRadius: '0.5rem',
          borderWidth: '1px',
        },
      },
      customCss: ['./src/styles/custom.css'],
      // The hero's image field is a logo slot — hardcoded 400×400. The override renders the home
      // page's banner at the size it is displayed at, and delegates the rest back to Starlight.
      components: {
        Hero: './src/components/Hero.astro',
      },
      sidebar: [
        {
          label: 'Getting Started',
          items: [
            { label: 'Introduction', slug: 'getting-started/introduction' },
            { label: 'Installation', slug: 'getting-started/installation' },
            { label: 'Choosing a target', slug: 'getting-started/target-platform' },
          ],
        },
        {
          label: 'Guides',
          items: [
            { label: 'Recipes', slug: 'guides/recipes' },
            { label: 'Running a generator', slug: 'guides/generators' },
            { label: 'The batch queue', slug: 'guides/batch' },
            { label: 'Deleting generated data', slug: 'guides/cleanup' },
            { label: 'Locales', slug: 'guides/locales' },
            { label: 'AI and MCP', slug: 'guides/mcp' },
            { label: 'WP-CLI', slug: 'guides/wp-cli' },
            { label: 'Settings', slug: 'guides/settings' },
          ],
        },
        {
          label: 'Reference',
          items: [
            { label: 'Architecture', slug: 'reference/architecture' },
            { label: 'Platform support', slug: 'reference/platform-support' },
            { label: 'Parameters', slug: 'reference/parameters' },
            { label: 'REST API', slug: 'reference/rest-api' },
            { label: 'Extension points', slug: 'reference/extension-points' },
            { label: 'External services', slug: 'reference/external-services' },
            { label: 'Support & troubleshooting', slug: 'reference/support' },
            { label: 'Contributing', slug: 'reference/contributing' },
            // Generated from the repository's CHANGELOG.md on predev/prebuild — see
            // scripts/sync-changelog.mjs. Listed last because it is the longest page on the site.
            { label: 'Changelog', slug: 'reference/changelog' },
          ],
        },
      ],
    }),
  ],
})
