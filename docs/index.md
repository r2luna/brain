---
layout: home

hero:
  name: Brain
  text: Process-Driven Architecture for Laravel
  tagline: Organize your business logic with Processes, Tasks, and Queries.
  actions:
    - theme: brand
      text: Get Started
      link: /getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/r2luna/brain

features:
  - icon: 🎯
    title: Domain-Driven Structure
    details: Organize your application into clear domains with proper architecture — optional and flexible.
  - icon: 🔄
    title: Process Orchestration
    details: Chain multiple tasks in a database transaction. Support for queues, sub-processes, and conditional execution.
  - icon: ⚡
    title: Reusable Tasks
    details: Build small, focused units of work. Reuse them across processes, validate inputs, and queue when needed.
  - icon: 🔍
    title: Query Objects
    details: Dedicated read-only classes for fetching data. Clean, testable, and easy to invoke.
  - icon: 📊
    title: Built-in Observability
    details: Track execution with events, logging, and the brain:show command to visualize your entire architecture.
  - icon: 🔒
    title: Sensitive Data Protection
    details: Mark payload properties as sensitive — automatically redacted in logs, JSON, and debug output.
---
