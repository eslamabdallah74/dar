# Nook Mate – Implementation Tasks

You are working on an existing production application called **Nook Mate**.

Your task is to implement the following four features in the existing application.

## 1. Project Context

The current technology stack:

- Backend: Laravel 12
- PHP: 8.4
- Frontend: Inertia.js v2
- Vue.js 3 Composition API
- TypeScript
- Tailwind CSS
- Database: MySQL

The application is already running in production and contains real user data.

**This is an enhancement to an existing application, NOT a new project.**

Before implementing anything, inspect the existing codebase, understand its architecture, database structure, relationships, authorization, validation rules, reusable components, and existing UI patterns.

Do not assume how something works without checking the actual implementation.

---

## 2. Mandatory Development Rules

These rules apply to ALL four tasks.

### Production Database Safety

1. Never modify existing migrations that may have already been executed in production.
2. Every required database change must be introduced through a new migration.
3. Migrations must preserve existing production data.
4. Avoid destructive database operations.
5. Do not drop or recreate existing tables or columns.
6. Make migrations safe for databases containing existing records.
7. Consider nullable columns, default values, indexes, and foreign key constraints carefully.
8. Never execute production migrations or destructive database commands.
9. Do not use production data for testing or modify real production records.

### Existing Architecture

- Follow the existing Laravel architecture and conventions.
- Reuse existing services, actions, composables, components, and validation mechanisms.
- Follow existing authorization and workspace isolation rules.
- Preserve existing business logic.
- Do not introduce unnecessary dependencies or libraries.
- Avoid unnecessary refactoring.
- Do not duplicate existing functionality.

### UI/UX

- Preserve the current Nook Mate design system.
- Reuse existing UI components.
- Maintain Arabic RTL and English LTR support.
- Respect the existing localization system.
- Do not introduce a new design system.
- Do not perform a redesign.

### Important: No Assumptions

If a requirement is unclear, inspect the existing implementation first.

If a business decision is still unclear after inspecting the code, STOP and ask for clarification before implementing that part.

Never invent business rules or silently choose an implementation that could affect existing functionality.

---

## 3. Implementation Order

Implement the tasks in the following exact order:

1. Duplicate Unit / Entity
2. Edit Tenant Information
3. Tenant Status Indicator
4. Full Website Responsive Improvements

> [!IMPORTANT]
> - Work on one task at a time.
> - Do not implement multiple tasks simultaneously.
> - Complete the current task, run its tests, review the changes, and report completion before moving to the next task.
> - Keep changes isolated and avoid mixing unrelated implementations.
> - Do not start the next task while the current task has unresolved issues.

---

## TASK 1: Duplicate Unit / Entity

### Objective

Allow users to duplicate an existing unit using the existing creation wizard.

This feature must not be limited to apartments.

It should work with the existing supported unit types and their hierarchical relationships.

Examples include:

- Buildings
- Apartments
- Rooms
- Beds
- Shops
- Any other supported unit types in the current application.

First, inspect the existing Unit model, hierarchy, creation workflows, and supported entity types.

### Expected User Flow

1. The user opens an existing unit's details page.
2. A new action called **Duplicate** or **Create Duplicate** is available.
3. Clicking it opens the same creation wizard used to create that unit type.
4. The wizard is prefilled with the original unit's eligible data.
5. The user can review and modify the information.
6. The user proceeds through the existing steps normally.
7. On final submission, a completely new unit is created.

The duplicate must use the existing creation workflow rather than introducing a separate creation mechanism.

### Data Duplication Rules

Copy the relevant configuration and descriptive data from the original entity.

Examples:

- Name
- Description
- Unit attributes
- Available services and facilities
- Other existing configurable fields.
- Hierarchical structure and eligible child units.

#### Nested Units

The duplication must respect the existing hierarchy.

For example:

If an entity contains multiple child units, duplicating the parent should prepare the equivalent structure in the creation wizard.

If a room contains beds, the duplicated room should contain equivalent bed entries.

The same principle applies to other supported hierarchical unit types.

Do not hardcode apartment-specific behavior.

Inspect the actual relationships and implement a reusable solution that respects the existing unit hierarchy.

#### Images

Images MUST NOT be duplicated.

- Do not copy image files.
- Do not prefill the original images.
- Do not reuse original image references.
- New entities should have no inherited images.

#### Data That Must Not Be Duplicated

Never copy operational or transactional information, including:

- Existing tenants.
- Rental contracts.
- Payments.
- Expenses.
- Maintenance requests.
- Other records representing actual operations or ownership history.

The new entity must be independent of the original entity.

Only duplicate the appropriate entity configuration and structural data.

### Parent Relationships

Do not assume that every unit belongs to a building.

Some units may be standalone entities.

Preserve the appropriate parent relationship according to the existing unit model and creation rules.

The user must be able to duplicate standalone units without being forced to select a building.

### Automatic Name Generation

The application already validates unit names to prevent duplicates.

When opening the duplicate wizard, automatically suggest the next available numbered name.

Examples:

| Original Name | Suggested Duplicate Name |
|---|---|
| عمارة النور | عمارة النور 2 |
| عمارة النور 2 | عمارة النور 3 |
| عمارة النور 3 | عمارة النور 4 |
| Apartment A | Apartment A 2 |
| Apartment A 2 | Apartment A 3 |

Requirements:

1. The first duplicate should use suffix `2`.
2. If the original already has a numeric suffix, increment it.
3. Determine the next available suffix based on the existing naming and uniqueness rules.
4. Respect the actual uniqueness scope used by the application.
5. The user must be able to edit the suggested name.
6. Existing backend validation must remain active.
7. Never bypass uniqueness validation.

If the suggested name conflicts with an existing record, handle it safely and suggest an available name.

### Technical Requirements

- Reuse the existing creation wizard.
- Reuse existing Form Requests and validation rules.
- Reuse existing creation services/actions where possible.
- Do not create a parallel implementation of unit creation.
- Ensure nested units are submitted using the existing creation mechanism.
- Ensure the original entity remains completely unchanged.
- Respect workspace boundaries and authorization.

### Acceptance Criteria

- [ ] Duplicate works for all applicable unit types.
- [ ] Nested structures are duplicated correctly.
- [ ] Images are not duplicated.
- [ ] Operational data is not duplicated.
- [ ] Names are automatically suggested with an incremented suffix.
- [ ] Users can modify all editable fields before creation.
- [ ] The resulting entity is independent.
- [ ] Existing creation functionality continues working.
- [ ] No production data is lost or modified.
- [ ] Appropriate automated tests are added.

After completion, report the implementation and test results before proceeding.

---

## TASK 2: Edit Tenant Information

### Objective

Allow authorized administrators to edit tenant information after the tenant has been created.

Currently, users need the ability to correct or update tenant information without creating a new tenant.

### Requirements

Add an Edit action to the tenant details interface.

The administrator should be able to update all existing editable tenant information.

First, inspect the current tenant creation form and tenant model to identify all existing fields.

Do not create a new set of fields or introduce unnecessary tenant attributes.

#### Editable Information

All existing tenant profile fields should be editable, including:

- Tenant name.
- Mobile number.
- National ID.
- Address.
- Contact information.
- Other existing tenant profile fields.

Apply the same validation rules used during tenant creation.

#### National ID and Mobile Validation

Allow administrators to change existing mobile numbers and national IDs.

However:

- Preserve existing format validation.
- Preserve uniqueness validation.
- Ensure uniqueness rules correctly ignore the current tenant during updates.
- Do not allow duplicate values when uniqueness is required.

#### National ID Image Management

The administrator must be able to:

1. Upload an ID image if one does not exist.
2. Replace an existing ID image.
3. Delete the existing ID image.

Important implementation details:

- Use the existing storage mechanism.
- Validate uploaded files using appropriate existing rules.
- Remove old files safely when replacing or deleting them.
- Never delete a file that is still referenced by another record.
- Handle storage failures safely.
- Avoid leaving orphaned files.

#### Contracts Must Remain Untouched

> [!CAUTION]
> This requirement is extremely important.

Tenant editing must only modify tenant profile information.

Do not modify:

- Existing rental contracts.
- Contract dates.
- Rental amounts.
- Payment schedules.
- Unit assignments.
- Contract history.
- Any historical financial records.

Even if a tenant has an active contract, their profile information must remain editable.

Editing a tenant must not trigger contract updates.

#### Authorization

Follow the existing workspace and authorization rules.

An administrator must only be able to edit tenants belonging to their authorized workspace.

Do not introduce new permission systems.

### Acceptance Criteria

- [ ] Tenant information can be edited from the tenant details interface.
- [ ] All existing editable fields are supported.
- [ ] National ID and mobile validation work correctly.
- [ ] Existing values do not cause false uniqueness errors.
- [ ] ID images can be uploaded, replaced, and deleted.
- [ ] Active contracts remain unchanged.
- [ ] Tenant data remains isolated between workspaces.
- [ ] Existing tenant creation and other workflows continue working.
- [ ] Tests cover validation, authorization, image management, and contract preservation.

Report completion before proceeding.

---

## TASK 3: Tenant Status Indicator

### Objective

Introduce a simple manual visual indicator for tenants.

The purpose is to help property owners quickly identify the general status of each tenant.

This is a manual classification system, not an automated payment or behavior monitoring system.

### Available Statuses

Three statuses are required:

| Status | Color | Meaning |
|---|---|---|
| Green | Green | Good / Reliable tenant |
| Yellow | Yellow | Tenant needs attention |
| Red | Red | Tenant has issues |

Use appropriate colors consistent with the existing design system.

### Business Rules

1. The status is selected manually by the property owner.
2. The system must never automatically change the status.
3. The status must not depend on payment history.
4. The status must not depend on overdue rent.
5. No automated background jobs or scheduled processes should change it.
6. The status belongs to the tenant itself, not an individual rental contract.
7. Changing the status must not affect contracts or financial records.
8. Existing tenant notes must remain unchanged.

The same tenant must have the same status wherever their name appears.

### UI Requirements

Display a small visual status indicator/icon next to the tenant's name.

The indicator should appear everywhere tenant names are displayed throughout the application.

This includes, where applicable:

- Tenants listing.
- Tenant details.
- Contract pages.
- Payment pages.
- Unit details.
- Tables and related records.
- Other interfaces displaying tenant names.

Do not implement the indicator only on the tenants listing page.

#### Reusable Component

Prefer creating a small reusable Vue component for the tenant status indicator.

For example:

`TenantStatusIndicator.vue`

The component should:

- Accept the tenant status.
- Render the appropriate visual indicator.
- Support existing RTL/LTR layouts.
- Follow the current design system.
- Avoid unnecessary visual clutter.
- Provide an accessible label or tooltip explaining the status.

Reuse the component wherever tenant names are rendered.

#### Status Management

Provide a simple way for authorized administrators to change the tenant status.

Do not introduce complicated workflows.

The administrator should be able to switch between:

- Green
- Yellow
- Red

Preserve the existing tenant notes functionality.

### Database Considerations

Inspect the existing tenant schema first.

If a new field is required:

- Add it through a new migration.
- Preserve existing tenant records.
- Choose a safe initial state.
- Preserve compatibility with existing records and code.

If the initial status behavior is not clear from existing conventions, ask before choosing a default.

### Acceptance Criteria

- [ ] Three statuses are supported.
- [ ] Status changes are manual only.
- [ ] Status belongs to the tenant.
- [ ] The indicator appears beside tenant names throughout the application.
- [ ] Existing notes remain functional.
- [ ] No automated status changes are introduced.
- [ ] Existing tenant workflows continue working.
- [ ] Workspace isolation and authorization are preserved.
- [ ] Tests cover status persistence, authorization, and validation.

Report completion before proceeding.

---

## TASK 4: Full Website Responsive Improvements

### Objective

The current mobile responsive experience is poor and needs a comprehensive review.

The goal is to make the entire Nook Mate website properly responsive across mobile, tablet, laptop, and desktop screens.

**This is a responsive improvement task, NOT a redesign.**

Preserve the existing visual identity, colors, typography, spacing principles, and overall layout.

Do not introduce a new design.

### Scope

Review and improve the entire application.

Do not limit the work to the dashboard or a few frequently used pages.

#### A. Shared Layout Components

Inspect and fix:

- Sidebar.
- Navbar.
- Footer.
- Main content layout.
- Navigation menus.
- Dropdowns.
- Modals.
- Notifications.
- Alerts.
- Breadcrumbs.
- Page headers.
- Shared buttons and form components.

The mobile sidebar must be usable and must not interfere with the page content.

Ensure navigation works correctly on small screens.

#### B. Dashboard

Review every dashboard page, including:

- Dashboard overview.
- Properties.
- Buildings.
- Apartments.
- Rooms.
- Beds.
- Shops.
- Tenants.
- Contracts.
- Payments.
- Expenses.
- Maintenance.
- Reports.
- Settings.
- Other existing application pages.

Do not assume that the list above represents every page.

Discover and inspect the actual application routes.

#### C. Forms and Wizards

Review all existing forms and multi-step creation workflows.

Pay special attention to:

- Create forms.
- Edit forms.
- Multi-step wizards.
- Validation messages.
- File upload fields.
- Select inputs.
- Date pickers.
- Action buttons.

Ensure forms remain usable without horizontal scrolling on mobile.

#### D. Tables and Data Lists

Tables are particularly important.

Review:

- Column overflow.
- Action buttons.
- Long text.
- Pagination.
- Search fields.
- Filters.
- Sorting.
- Responsive table layouts.

Use an appropriate responsive approach for each table.

Possible approaches include:

- Responsive horizontal scrolling when appropriate.
- Mobile-specific card layouts when justified.
- Hiding nonessential columns on small screens where safe.

Do not remove important information just to make a table fit.

#### E. Landing Page

The landing page must also be included.

Review all existing sections, including:

- Hero section.
- Navigation.
- Features.
- Benefits.
- Pricing.
- Testimonials.
- Call-to-action sections.
- Footer.
- Other existing landing page sections.

Ensure the entire landing page works correctly on mobile.

#### F. Responsive Breakpoints

Test the application at these viewport widths:

| Device | Width |
|---|---:|
| Small mobile | 320px |
| Mobile | 375px |
| Large mobile | 390px |
| Large mobile | 430px |
| Tablet | 768px |
| Small laptop | 1024px |
| Desktop | 1440px |

These are testing widths, not mandatory breakpoints.

Use the existing Tailwind breakpoint conventions where possible.

#### G. Responsive Quality Requirements

The final implementation must ensure:

- No unintended horizontal page overflow.
- No elements extending outside the viewport.
- No overlapping components.
- No clipped text or buttons.
- No broken navigation.
- No unusable forms.
- No inaccessible modal actions.
- Proper spacing on mobile.
- Appropriate touch target sizes.
- Proper sidebar behavior.
- Proper table behavior.
- Consistent layouts across different screen sizes.

#### H. RTL and LTR Support

The application supports Arabic and English.

Test responsive behavior in both directions:

- Arabic RTL.
- English LTR.

Do not introduce direction-specific layout bugs.

Pay attention to:

- Sidebar positioning.
- Navigation alignment.
- Icons.
- Margins and padding.
- Dropdown positioning.
- Table alignment.
- Modal positioning.

Prefer direction-aware styling where appropriate.

#### I. Technical Approach

1. Inspect shared layouts and components first.
2. Identify reusable responsive issues.
3. Fix shared components before making unnecessary individual page changes.
4. Review every existing route.
5. Reuse existing components and Tailwind conventions.
6. Avoid introducing new dependencies.
7. Preserve desktop appearance unless a change is necessary to fix a responsive issue.

Do not simply add `overflow-x-hidden` to hide layout problems.

Fix the actual source of horizontal overflow.

#### J. Testing

Use browser-based testing or the existing available testing tools.

Test representative pages and components at all specified viewport widths.

Where possible, automate viewport testing.

If browser automation is unavailable, use the existing available tooling and report exactly which pages and viewport sizes were actually verified.

Do not claim that every page has been tested if only a subset was inspected.

### Acceptance Criteria

- [ ] The entire website has been reviewed.
- [ ] All existing application routes have been considered.
- [ ] Landing page is responsive.
- [ ] Shared layout components work correctly.
- [ ] Tables and forms are usable on mobile.
- [ ] Arabic RTL and English LTR work correctly.
- [ ] No unintended horizontal overflow.
- [ ] Desktop appearance is preserved.
- [ ] No unnecessary redesign has been introduced.

Provide a final responsive audit report with:

1. Pages reviewed.
2. Issues discovered.
3. Issues fixed.
4. Shared components modified.
5. Viewports tested.
6. Remaining issues, if any.

---

## 4. Final Quality Assurance

After implementing each task:

1. Review all modified files.
2. Check for unnecessary changes.
3. Run relevant automated tests.
4. Run static analysis where available.
5. Check frontend TypeScript errors.
6. Check Laravel validation and authorization.
7. Verify existing functionality has not regressed.
8. Review database migrations for production safety.
9. Verify workspace isolation.
10. Report any unresolved issues.

Do not mark a task complete if its critical acceptance criteria are not satisfied.

### Required Completion Report

After each task, provide:

#### Implementation Summary
What was implemented.

#### Files Changed
List important files created or modified.

#### Database Changes
List new migrations and explain their purpose, or state that no database changes were required.

#### Testing
List tests executed and their results.

#### Security and Production Safety
Explain how authorization, workspace isolation, and existing data were protected.

#### Remaining Issues
List anything incomplete or requiring clarification.

#### Completion Status
Clearly state whether the task is complete.

Only then proceed to the next task.

---

## 5. Explicitly Out of Scope

> [!WARNING]
> The following feature is NOT part of this implementation:
>
> **Payment Information / Payment Methods**
>
> This includes:
> - InstaPay.
> - Vodafone Cash.
> - Custom payment links.
>
> Do not implement, modify, or prepare database structures for this feature.
> It will be handled separately in the future.

---

## Final Instruction

Your priority is correctness, production safety, and consistency with the existing Nook Mate architecture.

Do not optimize for the smallest amount of code at the expense of correctness.

Do not overengineer the implementation.

Do not redesign existing interfaces.

Implement exactly what is described, reuse existing functionality wherever possible, and ask before making business decisions that are not explicitly defined.

Start by inspecting the existing project and preparing a short implementation assessment for **Task 1: Duplicate Unit / Entity**.

Do not start implementing Task 2, Task 3, or Task 4 until Task 1 has been completed and reviewed.

