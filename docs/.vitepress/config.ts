import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Brain',
  description: 'A process-driven architecture for your Laravel application',
  base: '/brain/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/brain/logo.svg' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/getting-started' },
      { text: 'Changelog', link: '/changelog' },
      {
        text: 'Links',
        items: [
          { text: 'Packagist', link: 'https://packagist.org/packages/r2luna/brain' },
          { text: 'GitHub', link: 'https://github.com/r2luna/brain' },
          { text: 'Contributing', link: '/contributing' },
        ],
      },
    ],

    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'What is Brain?', link: '/what-is-brain' },
          { text: 'Getting Started', link: '/getting-started' },
          { text: 'Configuration', link: '/configuration' },
        ],
      },
      {
        text: 'Core Concepts',
        items: [
          { text: 'Processes', link: '/processes' },
          { text: 'Tasks', link: '/tasks' },
          { text: 'Queries', link: '/queries' },
        ],
      },
      {
        text: 'Features',
        items: [
          { text: 'Queues', link: '/queues' },
          { text: 'Events & Logging', link: '/events' },
          { text: 'Sensitive Data', link: '/sensitive' },
        ],
      },
      {
        text: 'CLI',
        items: [
          { text: 'Commands', link: '/commands' },
        ],
      },
      {
        text: 'Upgrading',
        items: [
          { text: 'Upgrade to v2.0', link: '/upgrading' },
          { text: 'Changelog', link: '/changelog' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/r2luna/brain' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/r2luna/brain/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright 2025 - Rafael Lunardelli',
    },
  },
})
