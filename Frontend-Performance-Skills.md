# Frontend Performance & Real-Time UX Optimization Prompt

You are a Senior Software Architect, Senior Frontend Engineer, UX Engineer, and Performance Optimization Specialist.

Your task is to analyze the entire application, including all modules, pages, components, routes, layouts, tables, forms, dashboards, and user workflows. Create a detailed implementation plan and perform the necessary refactoring to achieve a modern, high-performance, real-time user experience.

## Primary Objectives

### 1. Eliminate Full Page Reloads

Analyze all pages and identify areas where browser refreshes occur.

Requirements:

* Load and update only the necessary content sections.
* Convert page refreshes into dynamic content updates.
* Maintain browser history and navigation functionality.
* Preserve scroll position whenever possible.
* Ensure seamless transitions between views.

### 2. Implement Skeleton Loading States

Scan all pages and components that retrieve data asynchronously.

Requirements:

* Replace generic loading spinners with skeleton loaders.
* Create skeleton placeholders matching the actual content layout.
* Apply skeleton loaders to:

  * Dashboard widgets
  * Tables
  * Cards
  * Charts
  * Forms
  * Profile pages
  * Modals
  * Reports
* Ensure loading states feel natural and responsive.

### 3. DOM-Based User Experience

Refactor all interactions to operate directly within the browser.

Requirements:

* Avoid full browser refreshes.
* Use reactive state updates.
* Update UI elements dynamically.
* Preserve user context.
* Prevent unnecessary re-rendering.
* Optimize rendering performance.

### 4. Implement Real-Time Architecture

Analyze all modules that require live updates.

Examples:

* Notifications
* Chat
* Activity Logs
* User Presence
* Status Monitoring
* Dashboards
* Task Tracking
* System Monitoring

Requirements:

* Do not use repeated fetch polling for real-time features.
* Replace polling mechanisms with WebSockets.
* Use event-driven architecture.
* Establish persistent WebSocket connections.
* Implement automatic reconnection handling.
* Handle connection failures gracefully.
* Create scalable real-time communication patterns.

For each real-time module provide:

* Current implementation
* Recommended architecture
* Required backend changes
* Required frontend changes
* Expected performance improvements

### 5. Optimize Data Tables

Analyze all tables and data grids.

Requirements:

Use API fetch requests only when:

* Initial data loading
* Manual refresh actions
* Pagination changes
* Filter changes
* Sorting changes
* Search operations

Avoid:

* Continuous polling
* Excessive network requests
* Duplicate API calls

Implement:

* Lazy loading
* Virtual scrolling where appropriate
* Server-side pagination
* Efficient caching
* Background refresh mechanisms

### 6. Implement 5-Second Undo Pattern

Analyze all update and delete operations.

Requirements:

For Update Actions:

* Display success notification immediately.
* Start 5-second countdown.
* Allow user to cancel.
* Commit database transaction after countdown expires.

For Delete Actions:

* Mark record as pending deletion.
* Display Undo notification.
* Start 5-second countdown.
* Restore instantly if canceled.
* Permanently delete only after countdown completion.

Provide:

* Database strategy
* Backend implementation
* Frontend implementation
* Queue/job recommendations
* Recovery mechanism

### 7. Implement Optimistic UI Updates

Identify all suitable CRUD operations.

Requirements:

* Update interface immediately.
* Sync changes in background.
* Rollback automatically if server validation fails.
* Show clear feedback to users.
* Maintain data consistency.

### 8. State Management Review

Analyze current state management architecture.

Requirements:

* Identify duplicated states.
* Identify unnecessary API requests.
* Identify inefficient re-renders.
* Recommend centralized state management.

Provide:

* Current issues
* Proposed architecture
* Migration plan
* Performance impact

### 9. Notification Strategy

Implement non-intrusive user feedback.

Requirements:

Use toast notifications for:

* Create actions
* Update actions
* Delete actions
* Import actions
* Export actions
* Sync operations
* Background processes

Provide:

* Notification standards
* Notification priority levels
* UI behavior recommendations

### 10. Client-Side Caching Strategy

Analyze all API endpoints.

Requirements:

Categorize:

* Frequently accessed data
* Static data
* Dynamic data
* Real-time data

Recommend:

* Cache duration
* Invalidation rules
* Refresh strategy
* Storage approach

### 11. Standardized UI States

Ensure all modules consistently support:

* Loading state
* Skeleton state
* Success state
* Error state
* Empty state
* Offline state

Provide reusable component recommendations.

### 12. Performance Audit

Perform a comprehensive audit.

Identify:

* Slow components
* Large bundle sizes
* Duplicate requests
* Memory leaks
* Unnecessary re-renders
* Blocking operations
* Inefficient queries
* WebSocket opportunities

Provide:

* Severity level
* Impact
* Recommended fix
* Estimated effort
* Expected performance gain


