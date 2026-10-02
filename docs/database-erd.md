# VOID Database ERD

This diagram is generated from the Laravel migrations in `database/migrations/`. It describes the application schema expected on Render PostgreSQL; it is not a live introspection of the remote database.

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string username UK
        string email UK
        timestamp email_verified_at
        string password
        enum role
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    CUSTOMERS {
        bigint id PK
        string username UK
        string email UK
        string password
        timestamp email_verified_at
        string remember_token
        timestamp created_at
        timestamp updated_at
    }

    CUSTOMER_ADDRESSES {
        bigint id PK
        bigint customer_id FK
        string recipient_name
        string phone
        string street
        string city
        string province
        string postal_code
        string country
        timestamp created_at
        timestamp updated_at
    }

    PRODUCTS {
        bigint id PK
        string name
        string slug UK
        string category
        text description
        string image
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    PRODUCT_VARIANTS {
        bigint id PK
        bigint product_id FK
        string size
        string sku UK
        decimal price
        integer stock
        timestamp created_at
        timestamp updated_at
    }

    ORDERS {
        bigint id PK
        string order_ref UK
        enum channel
        enum status
        bigint customer_id FK
        string customer_name
        bigint cashier_id FK
        decimal subtotal
        decimal discount_amount
        decimal shipping_fee
        decimal tax_amount
        decimal total_amount
        string payment_method
        string proof_of_payment
        text shipping_address
        string notes
        timestamp placed_at
        timestamp completed_at
        timestamp created_at
        timestamp updated_at
    }

    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        bigint product_variant_id FK
        string product_name
        string product_size
        decimal unit_price
        integer quantity
        decimal line_total
        timestamp created_at
        timestamp updated_at
    }

    ORDER_STATUS_HISTORIES {
        bigint id PK
        bigint order_id FK
        string from_status
        string to_status
        string note
        bigint changed_by FK
        timestamp created_at
        timestamp updated_at
    }

    PAYMENTS {
        bigint id PK
        bigint order_id FK
        string method
        decimal amount_due
        decimal amount_tendered
        decimal change_due
        string reference
        string proof_path
        bigint received_by FK
        timestamp paid_at
        timestamp created_at
        timestamp updated_at
    }

    RECEIPTS {
        bigint id PK
        bigint order_id FK UK
        string receipt_number UK
        bigint issued_by FK
        timestamp issued_at
        integer print_count
        timestamp created_at
        timestamp updated_at
    }

    HELD_SALES {
        bigint id PK
        string reference
        bigint cashier_id FK
        bigint customer_id FK
        string customer_name
        json items
        integer item_count
        decimal total
        timestamp created_at
        timestamp updated_at
    }

    PRODUCT_REVIEWS {
        bigint id PK
        bigint product_id FK
        bigint customer_id FK
        bigint order_id FK
        tinyint rating
        text body
        boolean is_approved
        boolean verified_purchase
        timestamp created_at
        timestamp updated_at
    }

    PASSWORD_RESET_TOKENS {
        string email PK
        string token
        timestamp created_at
    }

    SESSIONS {
        string id PK
        bigint user_id FK
        string ip_address
        text user_agent
        text payload
        integer last_activity
    }

    CACHE {
        string key PK
        text value
        bigint expiration
    }

    CACHE_LOCKS {
        string key PK
        string owner
        bigint expiration
    }

    JOBS {
        bigint id PK
        string queue
        text payload
        smallint attempts
        integer reserved_at
        integer available_at
        integer created_at
    }

    JOB_BATCHES {
        string id PK
        string name
        integer total_jobs
        integer pending_jobs
        integer failed_jobs
        text failed_job_ids
        text options
        integer cancelled_at
        integer created_at
        integer finished_at
    }

    FAILED_JOBS {
        bigint id PK
        string uuid UK
        string connection
        string queue
        text payload
        text exception
        timestamp failed_at
    }

    CUSTOMERS ||--o{ CUSTOMER_ADDRESSES : saves
    CUSTOMERS ||--o{ ORDERS : places
    CUSTOMERS ||--o{ HELD_SALES : associated_with
    CUSTOMERS ||--o{ PRODUCT_REVIEWS : writes

    USERS ||--o{ ORDERS : handles
    USERS ||--o{ ORDER_STATUS_HISTORIES : changes
    USERS ||--o{ PAYMENTS : receives
    USERS ||--o{ RECEIPTS : issues
    USERS ||--o{ HELD_SALES : holds
    USERS ||--o{ SESSIONS : authenticates

    PRODUCTS ||--o{ PRODUCT_VARIANTS : has
    PRODUCTS ||--o{ ORDER_ITEMS : appears_in
    PRODUCTS ||--o{ PRODUCT_REVIEWS : receives

    ORDERS ||--o{ ORDER_ITEMS : contains
    ORDERS ||--o{ ORDER_STATUS_HISTORIES : records
    ORDERS ||--o{ PAYMENTS : has
    ORDERS ||--o| RECEIPTS : produces
    ORDERS ||--o{ PRODUCT_REVIEWS : verifies

    PRODUCT_VARIANTS ||--o{ ORDER_ITEMS : sold_as
}
```

## Important Constraints

- `products.slug`, `customers.username`, `customers.email`, `users.username`, `users.email`, `product_variants.sku`, `orders.order_ref`, and `receipts.receipt_number` are unique.
- A product variant is unique per product and size.
- A customer can review a product only once: `UNIQUE(customer_id, product_id)`.
- Product reviews are tied to a completed order and marked as verified purchases.
- Customer addresses, order items, order histories, payments, receipts, and product reviews are deleted when their owning parent is deleted, according to the migration foreign-key rules.
- An order may have multiple payment records, while `Order::payment()` selects the latest payment. An order has at most one receipt.
- `order_items` intentionally stores product name, size, price, and totals as snapshots so historical orders remain readable after catalog changes.

## Render PostgreSQL Inspection

You can compare the live Render database against this diagram with:

```sql
SELECT
    table_name,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_schema = 'public'
ORDER BY table_name, ordinal_position;
```

For foreign keys:

```sql
SELECT
    tc.table_name,
    kcu.column_name,
    ccu.table_name AS referenced_table,
    ccu.column_name AS referenced_column
FROM information_schema.table_constraints AS tc
JOIN information_schema.key_column_usage AS kcu
    ON tc.constraint_name = kcu.constraint_name
   AND tc.table_schema = kcu.table_schema
JOIN information_schema.constraint_column_usage AS ccu
    ON ccu.constraint_name = tc.constraint_name
   AND ccu.table_schema = tc.table_schema
WHERE tc.constraint_type = 'FOREIGN KEY'
  AND tc.table_schema = 'public'
ORDER BY tc.table_name, kcu.column_name;
```
