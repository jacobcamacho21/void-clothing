# VOID Retail System Agile Sprint Plan

This sprint plan describes the iterative development approach used for the VOID Retail System research project. It follows an Agile methodology in which the system is developed in short, time-boxed iterations. Each sprint produces a demonstrable increment, receives feedback, and refines the backlog for the next iteration.

The plan follows the documented research scope: storefront shopping, customer accounts, online ordering, staff back office, inventory, order review, product reviews, customer support, and automatic updates. The POS register is treated as a future extension and is not included in the active research scope.

## 1. Agile Approach

| Practice | Application in the project |
| --- | --- |
| Sprint length | Two weeks per sprint |
| Product backlog | Use cases, validation rules, security requirements, and interface requirements |
| Sprint planning | Select prioritized backlog items that can produce a usable increment |
| Daily coordination | Short progress update covering completed work, current work, and blockers |
| Sprint review | Demonstrate the completed increment using representative user scenarios |
| Sprint retrospective | Record what worked, what caused difficulty, and what should change in the next sprint |
| Definition of done | Implemented, validated, integrated, tested, documented, and demonstrated |
| Feedback source | Researchers, intended users, advisers, and acceptance-test results |

## 2. Team Responsibilities

| Role | Responsibility |
| --- | --- |
| Product owner / researcher | Prioritizes requirements, clarifies expected behavior, and accepts sprint results |
| Scrum facilitator | Coordinates sprint activities, tracks blockers, and maintains the sprint record |
| Development team | Designs, implements, tests, and documents the system increment |
| Stakeholders / evaluators | Review demonstrations and provide usability and functional feedback |

## 3. Sprint Summary

| Sprint | Focus | Main result |
| --- | --- | --- |
| Sprint 0 | Inception and requirements | Agreed scope, backlog, architecture, and acceptance criteria |
| Sprint 1 | Catalog and storefront foundation | Browsable product catalog with search and product details |
| Sprint 2 | Authentication and customer account | Secure customer access, registration, verification, and account data |
| Sprint 3 | Cart, checkout, and pricing | Validated online order creation with payment proof and authoritative totals |
| Sprint 4 | Staff operations and inventory | Dashboard, inventory maintenance, payment review, and order workflow |
| Sprint 5 | Reviews, support, and live updates | Customer engagement and operational communication features |
| Sprint 6 | Integration, quality assurance, and release | Complete tested system, research evidence, and deployment package |

## 4. Detailed Sprint Backlog

### Sprint 0: Inception and Requirements

**Duration:** Weeks 1-2  
**Goal:** Establish a shared understanding of the problem, users, scope, and technical direction before implementation.

**Backlog items**

- Identify guest, customer, staff, administrator, mail, storage, and database actors.
- Confirm the system boundary and research scope.
- Document the storefront, customer, staff, and automated system use cases.
- Define the order lifecycle, stock rules, access roles, and acceptance criteria.
- Select Laravel, the database platform, authentication guards, file storage, and email integration.
- Prepare the initial data model and development environment.

**Deliverables**

- Approved project scope and objectives.
- Prioritized product backlog.
- Use-case document and initial system boundary diagram.
- Initial database ERD and architecture decisions.
- Sprint schedule and risk register.

**Acceptance criteria**

- Every in-scope actor has at least one documented use case.
- The research scope clearly excludes the POS register from the active evaluation.
- Core order, stock, authentication, and authorization rules are agreed upon.
- The team can run the Laravel application and database locally.

### Sprint 1: Catalog and Storefront Foundation

**Duration:** Weeks 3-4  
**Goal:** Allow guests and customers to discover and inspect active products.

**Use cases:** UC-01, UC-02, UC-03

**Backlog items**

- Create the product and product-variant structure.
- Display active products with images, categories, prices, sizes, and stock state.
- Implement case-insensitive product search.
- Implement product detail pages with descriptions, image gallery, size options, and size chart.
- Define empty, unavailable, and sold-out states.
- Preserve the approved storefront visual design across catalog pages.

**Deliverables**

- Working storefront catalog.
- Product search results page.
- Product detail page.
- Catalog and variant database tables.
- Initial catalog acceptance tests.

**Acceptance criteria**

- Guests can browse active products without signing in.
- Search matches product name, description, and category without case-sensitive spelling.
- Product details show the correct price, size, image, and stock-dependent state.
- Inactive or missing products are not presented as purchasable items.

### Sprint 2: Authentication and Customer Account

**Duration:** Weeks 5-6  
**Goal:** Provide secure customer access and the account functions required for online purchasing.

**Use cases:** UC-05, UC-06, UC-09, and the account portion of the system boundary.

**Backlog items**

- Implement customer registration and sign-in/sign-out.
- Configure separate customer and staff authentication guards.
- Add password hashing, session regeneration, throttling, and authorization checks.
- Implement email verification and password recovery support.
- Implement customer profile and address management.
- Display the customer's own order history and order details.
- Prevent access to another customer's orders or account data.

**Deliverables**

- Customer registration and login screens.
- Customer account and address pages.
- Protected order-history and order-detail pages.
- Authentication and authorization tests.

**Acceptance criteria**

- A valid customer can register, sign in, sign out, and access protected account pages.
- Invalid, duplicate, inactive, or throttled authentication attempts are rejected safely.
- A customer can access only their own orders and account information.
- Staff and customer sessions remain independent.

### Sprint 3: Cart, Checkout, and Pricing

**Duration:** Weeks 7-8  
**Goal:** Enable a customer to prepare a valid cart and submit an online order.

**Use cases:** UC-04, UC-08, UC-20, and the online portion of UC-21.

**Backlog items**

- Implement session-based cart creation, quantity changes, removal, and clearing.
- Validate product variants and available quantities against the live catalog.
- Implement authoritative pricing for subtotal, shipping, tax, discounts, and total.
- Implement checkout validation for delivery information, payment method, terms, and proof of payment.
- Store orders, order items, payment information, and uploaded proof.
- Clear the cart only after a successful order transaction.
- Keep new online orders in `Pending` status without deducting stock.

**Deliverables**

- Working cart and checkout flow.
- Pricing service and totals display.
- Payment-proof upload flow.
- Pending online order with customer confirmation.
- Cart, pricing, validation, and checkout tests.

**Acceptance criteria**

- A guest or customer cannot add more units than currently available.
- Prices are calculated from catalog data rather than trusted browser values.
- Invalid checkout data does not create an order or partially clear the cart.
- A valid checkout creates one pending order with its items and payment proof.
- Stock is unchanged while the order is pending.

### Sprint 4: Staff Operations and Inventory

**Duration:** Weeks 9-10  
**Goal:** Give staff the tools to review orders, maintain inventory, and complete the controlled order lifecycle.

**Use cases:** UC-12, UC-13, UC-14, UC-15, UC-16, UC-17, UC-18, UC-19, and UC-21.

**Backlog items**

- Implement staff dashboard metrics and date filtering.
- Implement searchable inventory with low-stock and out-of-stock indicators.
- Implement role-based inventory, customer, staff, and order permissions.
- Implement order desk search, filtering, detail view, and status history.
- Implement payment-proof review and approval/rejection.
- Implement legal order status transitions.
- Deduct stock when an order begins holding stock and restore it when stock is released.
- Add atomic stock updates, transaction rollback, and insufficient-stock handling.
- Issue receipts when orders reach completed status.

**Deliverables**

- Staff dashboard, inventory, and order desk.
- Payment-proof review workflow.
- Role-based administrator controls.
- Stock deduction and restoration behavior.
- Receipt and status-history records.
- Back-office authorization and workflow tests.

**Acceptance criteria**

- Staff can review payment proof and make only permitted status changes.
- Administrators can manage protected customer, staff, and destructive order actions.
- Approval deducts the ordered quantity exactly once.
- Rejection, cancellation, deletion, or return to pending restores stock only when the order held it.
- Insufficient stock rejects approval without a partial status or stock update.
- Completed orders have a preserved receipt and status history.

### Sprint 5: Reviews, Support, and Automatic Updates

**Duration:** Weeks 11-12  
**Goal:** Complete the customer engagement features and keep open pages synchronized with current data.

**Use cases:** UC-07, UC-11, UC-22, UC-23, UC-24.

**Backlog items**

- Implement customer support form validation and email delivery.
- Implement verified-purchase product reviews and ratings.
- Enforce one review per customer/product pair.
- Add review approval and storefront visibility rules.
- Implement receipt preservation and reprint behavior.
- Implement the update-version endpoint and safe polling refresh behavior.
- Prevent active forms from being interrupted by automatic refreshes.
- Add email, review, receipt, and polling failure states.

**Deliverables**

- Customer support contact flow.
- Verified product review flow.
- Review moderation and storefront display.
- Receipt reprint behavior.
- Automatic update notification and refresh behavior.
- Integration tests for customer engagement features.

**Acceptance criteria**

- Valid support messages reach the configured recipient with the customer's address as Reply-To.
- Only a customer with a completed purchase can submit a verified review.
- Duplicate reviews for the same customer and product are rejected.
- Receipt reprints preserve the original receipt number and record print history.
- Open pages detect relevant data changes without interrupting active forms.

### Sprint 6: Integration, Quality Assurance, and Release

**Duration:** Weeks 13-14  
**Goal:** Validate the complete system against the research objectives and prepare the final implementation and evidence.

**Backlog items**

- Run end-to-end scenarios from registration through completed order and review.
- Run role-based authorization and negative-path testing.
- Test stock consistency across approval, rejection, cancellation, deletion, and insufficient-stock cases.
- Test responsive storefront and back-office layouts.
- Review validation messages, empty states, accessibility labels, and error handling.
- Run the complete automated test suite and code-quality checks.
- Document deployment, configuration, backup, and operational procedures.
- Collect sprint-review feedback and record final changes.

**Deliverables**

- Integrated release candidate.
- Test results and defect log.
- Updated use cases, ERD, and system documentation.
- User acceptance evidence and evaluation notes.
- Deployment-ready application and research-paper implementation results.

**Acceptance criteria**

- All in-scope use cases have a demonstrated happy path and at least one alternate or failure path.
- Critical authorization and stock-integrity tests pass.
- No unresolved high-severity defects remain for the evaluated scope.
- The final build can be installed, migrated, configured, and run using the documented procedure.
- Stakeholders accept the release candidate against the agreed acceptance criteria.

## 5. Definition of Done

A backlog item is considered done when:

1. The behavior is implemented in the appropriate application layer.
2. Validation and authorization rules are enforced server-side.
3. The user interface provides success, empty, and failure states where applicable.
4. Automated tests cover the normal path and important alternate paths.
5. The change is integrated with related modules without breaking existing use cases.
6. Relevant documentation, diagrams, or use-case descriptions are updated.
7. The feature is demonstrated during the sprint review and accepted by the product owner or researcher.

## 6. Sprint Review and Retrospective Record

The following record can be completed after each sprint review:

| Sprint | Increment demonstrated | Stakeholder feedback | Defects or risks | Improvement for next sprint |
| --- | --- | --- | --- | --- |
| Sprint 0 |  |  |  |  |
| Sprint 1 |  |  |  |  |
| Sprint 2 |  |  |  |  |
| Sprint 3 |  |  |  |  |
| Sprint 4 |  |  |  |  |
| Sprint 5 |  |  |  |  |
| Sprint 6 |  |  |  |  |

## 7. Research Contribution

Using sprints makes the implementation process observable and measurable. Each iteration produces a working increment that can be reviewed against the documented use cases. The sprint reviews provide opportunities to collect stakeholder feedback, while the retrospectives document process improvements. The final sprint consolidates functional testing, usability observations, security checks, and system documentation into evidence for evaluating whether the VOID Retail System meets its research objectives.
