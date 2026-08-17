# OneMallX Entity Relationship Diagram

> Generated from Laravel migrations on 2026-08-16.

Import **`docs/erd.dbml`** at [dbdiagram.io](https://dbdiagram.io/d) for the full interactive diagram.

## Core & Auth

```mermaid
erDiagram
    users {
        id bigint
        name varchar
        email varchar
        password varchar
        phoneNumber varchar
        status enum
        ...
    }
    password_reset_tokens {
        email varchar
        token varchar
        created_at timestamp
    }
    sessions {
        id varchar
        user_id bigint
        ip_address varchar
        user_agent text
        last_activity int
    }
    personal_access_tokens {
        id bigint
        tokenable morphs
        tokenable_type varchar
        tokenable_id bigint
        name text
        token varchar
        ...
    }
    permissions {
        id bigint
        name varchar
        guard_name varchar
        created_at timestamp
        updated_at timestamp
    }
    roles {
        id bigint
        name varchar
        guard_name varchar
        created_at timestamp
        updated_at timestamp
    }
    model_has_permissions {
        permission_id bigint
        model_type varchar
        model_id bigint
    }
    model_has_roles {
        role_id bigint
        model_type varchar
        model_id bigint
    }
    role_has_permissions {
        permission_id bigint
        role_id bigint
    }
    permissions ||--o{ model_has_permissions : "permission_id"
    roles ||--o{ model_has_roles : "role_id"
    permissions ||--o{ role_has_permissions : "permission_id"
    roles ||--o{ role_has_permissions : "role_id"
```

## Mall Structure

```mermaid
erDiagram
    malls {
        id bigint
        name varchar
        country varchar
        mallOwnerID bigint
        created_at timestamp
        updated_at timestamp
        ...
    }
    floors {
        id bigint
        name varchar
        number int
        mallID bigint
        created_at timestamp
        updated_at timestamp
        ...
    }
    areas {
        id bigint
        name varchar
        number int
        floorID bigint
        usageType varchar
        category varchar
        ...
    }
    business_categories {
        id bigint
        name varchar
        slug varchar
        type varchar
        icon varchar
        sortOrder int
        ...
    }
    locations {
        id bigint
        location varchar
        created_at timestamp
        updated_at timestamp
        deleted_at timestamp
    }
    floors ||--o{ areas : "floorID"
    business_categories ||--o{ areas : "categoryID"
    malls ||--o{ floors : "mallID"
```

## Stores & Products

```mermaid
erDiagram
    stores {
        id bigint
        name varchar
        storeOwnerID bigint
        areaID bigint
        description text
        status varchar
        ...
    }
    products {
        id bigint
        name varchar
        detail text
        price int
        quantity int
        storeID bigint
        ...
    }
    product_variants {
        id bigint
        productID bigint
        storeID bigint
        sku varchar
        barcode varchar
        name varchar
        ...
    }
    categories {
        id bigint
        storeID bigint
        parentID bigint
        name varchar
        slug varchar
        sortOrder int
        ...
    }
    product_category {
        id bigint
        productID bigint
        categoryID bigint
        created_at timestamp
        updated_at timestamp
    }
    attributes {
        id bigint
        storeID bigint
        name varchar
        code varchar
        sortOrder int
        created_at timestamp
        ...
    }
    attribute_values {
        id bigint
        attributeID bigint
        value varchar
        sortOrder int
        created_at timestamp
        updated_at timestamp
        ...
    }
    product_variant_attribute_value {
        id bigint
        productVariantID bigint
        attributeValueID bigint
        created_at timestamp
        updated_at timestamp
    }
    collections {
        id bigint
        storeID bigint
        name varchar
        image varchar
        description text
        created_at timestamp
        ...
    }
    collection_product {
        id bigint
        collectionID bigint
        productID bigint
        created_at timestamp
        updated_at timestamp
    }
    inventory_movements {
        id bigint
        productVariantID bigint
        storeID bigint
        type varchar
        quantityChange int
        quantityAfter int
        ...
    }
    attributes ||--o{ attribute_values : "attributeID"
    stores ||--o{ attributes : "storeID"
    stores ||--o{ categories : "storeID"
    categories ||--o{ categories : "parentID"
    collections ||--o{ collection_product : "collectionID"
    products ||--o{ collection_product : "productID"
    stores ||--o{ collections : "storeID"
    product_variants ||--o{ inventory_movements : "productVariantID"
    stores ||--o{ inventory_movements : "storeID"
    products ||--o{ product_category : "productID"
    categories ||--o{ product_category : "categoryID"
    product_variants ||--o{ product_variant_attribute_value : "productVariantID"
    attribute_values ||--o{ product_variant_attribute_value : "attributeValueID"
    products ||--o{ product_variants : "productID"
    stores ||--o{ product_variants : "storeID"
    stores ||--o{ products : "storeID"
```

## Services & Bookings

```mermaid
erDiagram
    services {
        id bigint
        name varchar
        serviceOwnerID bigint
        price int
        areaID bigint
        description text
        ...
    }
    service_items {
        id bigint
        serviceID bigint
        name varchar
        price int
        duration int
        created_at timestamp
        ...
    }
    employees {
        id bigint
        name varchar
        serviceID bigint
        daysOfWeek varchar
        created_at timestamp
        updated_at timestamp
        ...
    }
    employee_service_item {
        id bigint
        employeeID bigint
        serviceItemID bigint
        price int
    }
    employee_working_days {
        id bigint
        employee_id bigint
        created_at timestamp
        updated_at timestamp
    }
    service_working_days {
        id bigint
        service_id bigint
        created_at timestamp
        updated_at timestamp
    }
    bookings {
        id bigint
        serviceID bigint
        customerID bigint
        employeeID bigint
        date date
        entryNumber int
        ...
    }
    services ||--o{ bookings : "serviceID"
    employees ||--o{ bookings : "employeeID"
    service_items ||--o{ bookings : "serviceItemID"
    employees ||--o{ employee_service_item : "employeeID"
    service_items ||--o{ employee_service_item : "serviceItemID"
    employees ||--o{ employee_working_days : "employee_id"
    services ||--o{ employees : "serviceID"
    services ||--o{ service_items : "serviceID"
    services ||--o{ service_working_days : "service_id"
```

## Cart & Orders

```mermaid
erDiagram
    baskets {
        id bigint
        userID bigint
        status varchar
        totalPrice int
        created_at timestamp
        updated_at timestamp
    }
    basket_items {
        id bigint
        basketID bigint
        lineType varchar
        itemType varchar
        itemID bigint
        lineKey varchar
        ...
    }
    orders {
        id bigint
        basketID bigint
        userID bigint
        status varchar
        totalPrice int
        created_at timestamp
        ...
    }
    order_items {
        id bigint
        orderID bigint
        lineType varchar
        itemType varchar
        itemID bigint
        storeID bigint
        ...
    }
    customer_payments {
        id bigint
        customerID bigint
        orderID bigint
        methodID bigint
        price int
        created_at timestamp
        ...
    }
    payment_methods {
        id bigint
        providerName varchar
        active boolean
        created_at timestamp
        updated_at timestamp
    }
    baskets ||--o{ basket_items : "basketID"
    orders ||--o{ customer_payments : "orderID"
    payment_methods ||--o{ customer_payments : "methodID"
    orders ||--o{ order_items : "orderID"
    baskets ||--o{ orders : "basketID"
```

## Subscriptions

```mermaid
erDiagram
    store_subscription_plans {
        id bigint
        name varchar
        floorID bigint
        storeSpace int
        adsNumber int
        created_at timestamp
        ...
    }
    store_plan_prices {
        id bigint
        storeSubscriptionPlanID bigint
        duration int
        price int
        created_at timestamp
        updated_at timestamp
        ...
    }
    store_subscriptions {
        id bigint
        storeID bigint
        storeSubscriptionPlanID bigint
        planPriceID bigint
        startDate timestamp
        endDate timestamp
        ...
    }
    store_subscription_payments {
        id bigint
        subscriptionID bigint
        methodID bigint
        price int
        created_at timestamp
        updated_at timestamp
    }
    store_subscription_requests {
        id bigint
        applicantName varchar
        email varchar
        password varchar
        phoneNumber varchar
        storeName varchar
        ...
    }
    store_subscription_extension_requests {
        id bigint
        storeSubscriptionID bigint
        applicantNote text
        requestedByUserID bigint
        status varchar
        reviewedByUserID bigint
        ...
    }
    store_subscription_new_requests {
        id bigint
        storeSubscriptionID bigint
        requestedStoreSubscriptionPlanID bigint
        requestedPlanPriceID bigint
        applicantNote text
        requestedByUserID bigint
        ...
    }
    service_subscription_plans {
        id bigint
        name varchar
        floorID bigint
        serviceSpace int
        adsNumber int
        created_at timestamp
        ...
    }
    service_plan_prices {
        id bigint
        serviceSubscriptionPlanID bigint
        duration int
        price int
        created_at timestamp
        updated_at timestamp
        ...
    }
    service_subscriptions {
        id bigint
        serviceID bigint
        serviceSubscriptionPlanID bigint
        planPriceID bigint
        startDate timestamp
        endDate timestamp
        ...
    }
    service_subscription_payments {
        id bigint
        subscriptionID bigint
        methodID bigint
        price int
        created_at timestamp
        updated_at timestamp
    }
    service_subscription_requests {
        id bigint
        applicantName varchar
        email varchar
        password varchar
        phoneNumber varchar
        serviceName varchar
        ...
    }
    service_subscription_extension_requests {
        id bigint
        serviceSubscriptionID bigint
        applicantNote text
        requestedByUserID bigint
        status varchar
        reviewedByUserID bigint
        ...
    }
    service_subscription_new_requests {
        id bigint
        serviceSubscriptionID bigint
        requestedServiceSubscriptionPlanID bigint
        requestedPlanPriceID bigint
        applicantNote text
        requestedByUserID bigint
        ...
    }
    service_subscription_plans ||--o{ service_plan_prices : "serviceSubscriptionPlanID"
    service_subscriptions ||--o{ service_subscription_payments : "subscriptionID"
    service_subscription_plans ||--o{ service_subscription_requests : "serviceSubscriptionPlanID"
    service_plan_prices ||--o{ service_subscription_requests : "planPriceID"
    service_subscriptions ||--o{ service_subscription_requests : "createdSubscriptionID"
    service_subscription_plans ||--o{ service_subscriptions : "serviceSubscriptionPlanID"
    service_plan_prices ||--o{ service_subscriptions : "planPriceID"
    store_subscription_plans ||--o{ store_plan_prices : "storeSubscriptionPlanID"
    store_subscriptions ||--o{ store_subscription_payments : "subscriptionID"
    store_subscription_plans ||--o{ store_subscription_requests : "storeSubscriptionPlanID"
    store_plan_prices ||--o{ store_subscription_requests : "planPriceID"
    store_subscriptions ||--o{ store_subscription_requests : "createdSubscriptionID"
    store_subscription_plans ||--o{ store_subscriptions : "storeSubscriptionPlanID"
    store_plan_prices ||--o{ store_subscriptions : "planPriceID"
```

## Engagement

```mermaid
erDiagram
    favorite_products {
        id bigint
        userID bigint
        productID bigint
        created_at timestamp
        updated_at timestamp
    }
    rates {
        id bigint
        userID bigint
        rateableType varchar
        rateableID bigint
        comment text
        created_at timestamp
        ...
    }
    rate_reports {
        id bigint
        rateID bigint
        reporterUserID bigint
        status varchar
        created_at timestamp
        updated_at timestamp
    }
    advertisements {
        id bigint
        storeID bigint
        serviceID bigint
        title varchar
        image varchar
        targetType enum
        ...
    }
    media {
        id bigint
        mediableType varchar
        mediableID bigint
        fileType varchar
        url varchar
        created_at timestamp
        ...
    }
    rates ||--o{ rate_reports : "rateID"
```

## Analytics

```mermaid
erDiagram
    store_daily_stats {
        id bigint
        storeID bigint
        date date
        revenue int
        orders_count int
        customers_count int
        ...
    }
    service_daily_stats {
        id bigint
        serviceID bigint
        date date
        revenue int
        bookings_count int
        cancelled_bookings_count int
        ...
    }
    platform_daily_stats {
        id bigint
        date date
        user_registrations int
        orders_count int
        bookings_count int
        platform_revenue int
        ...
    }
```

## System

```mermaid
erDiagram
    cache {
        key varchar
        expiration int
    }
    cache_locks {
        key varchar
        owner varchar
        expiration int
    }
    jobs {
        id bigint
        queue varchar
        reserved_at int
        available_at int
        created_at int
    }
    job_batches {
        id varchar
        name varchar
        total_jobs int
        pending_jobs int
        failed_jobs int
        cancelled_at int
        ...
    }
    failed_jobs {
        id bigint
        uuid varchar
        connection text
        queue text
        failed_at timestamp
    }
```

## All Foreign Key Relationships

| From Table | Column | To Table |
|------------|--------|----------|
| `advertisements` | `storeID` | `stores` |
| `advertisements` | `serviceID` | `services` |
| `areas` | `floorID` | `floors` |
| `areas` | `categoryID` | `business_categories` |
| `attribute_values` | `attributeID` | `attributes` |
| `attributes` | `storeID` | `stores` |
| `basket_items` | `basketID` | `baskets` |
| `basket_items` | `employeeID` | `employees` |
| `baskets` | `userID` | `users` |
| `bookings` | `serviceID` | `services` |
| `bookings` | `customerID` | `users` |
| `bookings` | `employeeID` | `employees` |
| `bookings` | `serviceItemID` | `service_items` |
| `categories` | `storeID` | `stores` |
| `categories` | `parentID` | `categories` |
| `collection_product` | `collectionID` | `collections` |
| `collection_product` | `productID` | `products` |
| `collections` | `storeID` | `stores` |
| `customer_payments` | `customerID` | `users` |
| `customer_payments` | `orderID` | `orders` |
| `customer_payments` | `methodID` | `payment_methods` |
| `employee_service_item` | `employeeID` | `employees` |
| `employee_service_item` | `serviceItemID` | `service_items` |
| `employee_working_days` | `employee_id` | `employees` |
| `employees` | `serviceID` | `services` |
| `favorite_products` | `userID` | `users` |
| `favorite_products` | `productID` | `products` |
| `floors` | `mallID` | `malls` |
| `inventory_movements` | `productVariantID` | `product_variants` |
| `inventory_movements` | `storeID` | `stores` |
| `inventory_movements` | `createdBy` | `users` |
| `malls` | `mallOwnerID` | `users` |
| `model_has_permissions` | `permission_id` | `permissions` |
| `model_has_roles` | `role_id` | `roles` |
| `order_items` | `orderID` | `orders` |
| `order_items` | `storeID` | `stores` |
| `order_items` | `serviceID` | `services` |
| `order_items` | `employeeID` | `employees` |
| `orders` | `basketID` | `baskets` |
| `orders` | `userID` | `users` |
| `orders` | `locationID` | `locations` |
| `product_category` | `productID` | `products` |
| `product_category` | `categoryID` | `categories` |
| `product_variant_attribute_value` | `productVariantID` | `product_variants` |
| `product_variant_attribute_value` | `attributeValueID` | `attribute_values` |
| `product_variants` | `productID` | `products` |
| `product_variants` | `storeID` | `stores` |
| `products` | `storeID` | `stores` |
| `rate_reports` | `rateID` | `rates` |
| `rate_reports` | `reporterUserID` | `users` |
| `rates` | `userID` | `users` |
| `role_has_permissions` | `permission_id` | `permissions` |
| `role_has_permissions` | `role_id` | `roles` |
| `service_daily_stats` | `serviceID` | `services` |
| `service_items` | `serviceID` | `services` |
| `service_plan_prices` | `serviceSubscriptionPlanID` | `service_subscription_plans` |
| `service_subscription_payments` | `subscriptionID` | `service_subscriptions` |
| `service_subscription_payments` | `methodID` | `payment_methods` |
| `service_subscription_plans` | `floorID` | `floors` |
| `service_subscription_requests` | `areaID` | `areas` |
| `service_subscription_requests` | `locationID` | `locations` |
| `service_subscription_requests` | `serviceSubscriptionPlanID` | `service_subscription_plans` |
| `service_subscription_requests` | `planPriceID` | `service_plan_prices` |
| `service_subscription_requests` | `reviewedByUserID` | `users` |
| `service_subscription_requests` | `createdUserID` | `users` |
| `service_subscription_requests` | `createdServiceID` | `services` |
| `service_subscription_requests` | `createdSubscriptionID` | `service_subscriptions` |
| `service_subscriptions` | `serviceID` | `services` |
| `service_subscriptions` | `serviceSubscriptionPlanID` | `service_subscription_plans` |
| `service_subscriptions` | `planPriceID` | `service_plan_prices` |
| `service_working_days` | `service_id` | `services` |
| `services` | `serviceOwnerID` | `users` |
| `services` | `areaID` | `areas` |
| `services` | `locationID` | `locations` |
| `store_daily_stats` | `storeID` | `stores` |
| `store_plan_prices` | `storeSubscriptionPlanID` | `store_subscription_plans` |
| `store_subscription_payments` | `subscriptionID` | `store_subscriptions` |
| `store_subscription_payments` | `methodID` | `payment_methods` |
| `store_subscription_plans` | `floorID` | `floors` |
| `store_subscription_requests` | `areaID` | `areas` |
| `store_subscription_requests` | `storeSubscriptionPlanID` | `store_subscription_plans` |
| `store_subscription_requests` | `planPriceID` | `store_plan_prices` |
| `store_subscription_requests` | `reviewedByUserID` | `users` |
| `store_subscription_requests` | `createdUserID` | `users` |
| `store_subscription_requests` | `createdStoreID` | `stores` |
| `store_subscription_requests` | `createdSubscriptionID` | `store_subscriptions` |
| `store_subscriptions` | `storeID` | `stores` |
| `store_subscriptions` | `storeSubscriptionPlanID` | `store_subscription_plans` |
| `store_subscriptions` | `planPriceID` | `store_plan_prices` |
| `stores` | `storeOwnerID` | `users` |
| `stores` | `areaID` | `areas` |

## All Tables (65)

- `advertisements` — 13 columns, 2 outgoing FKs
- `areas` — 11 columns, 2 outgoing FKs
- `attribute_values` — 7 columns, 1 outgoing FKs
- `attributes` — 8 columns, 1 outgoing FKs
- `basket_items` — 12 columns, 2 outgoing FKs
- `baskets` — 6 columns, 1 outgoing FKs
- `bookings` — 13 columns, 4 outgoing FKs
- `business_categories` — 9 columns, 0 outgoing FKs
- `cache` — 2 columns, 0 outgoing FKs
- `cache_locks` — 3 columns, 0 outgoing FKs
- `categories` — 9 columns, 2 outgoing FKs
- `collection_product` — 5 columns, 2 outgoing FKs
- `collections` — 8 columns, 1 outgoing FKs
- `customer_payments` — 7 columns, 3 outgoing FKs
- `employee_service_item` — 4 columns, 2 outgoing FKs
- `employee_working_days` — 4 columns, 1 outgoing FKs
- `employees` — 10 columns, 1 outgoing FKs
- `failed_jobs` — 5 columns, 0 outgoing FKs
- `favorite_products` — 5 columns, 2 outgoing FKs
- `floors` — 7 columns, 1 outgoing FKs
- `inventory_movements` — 11 columns, 3 outgoing FKs
- `job_batches` — 8 columns, 0 outgoing FKs
- `jobs` — 5 columns, 0 outgoing FKs
- `locations` — 5 columns, 0 outgoing FKs
- `malls` — 7 columns, 1 outgoing FKs
- `media` — 8 columns, 0 outgoing FKs
- `model_has_permissions` — 3 columns, 1 outgoing FKs
- `model_has_roles` — 3 columns, 1 outgoing FKs
- `order_items` — 17 columns, 4 outgoing FKs
- `orders` — 8 columns, 3 outgoing FKs
- `password_reset_tokens` — 3 columns, 0 outgoing FKs
- `payment_methods` — 5 columns, 0 outgoing FKs
- `permissions` — 5 columns, 0 outgoing FKs
- `personal_access_tokens` — 11 columns, 0 outgoing FKs
- `platform_daily_stats` — 9 columns, 0 outgoing FKs
- `product_category` — 5 columns, 2 outgoing FKs
- `product_variant_attribute_value` — 5 columns, 2 outgoing FKs
- `product_variants` — 19 columns, 2 outgoing FKs
- `products` — 14 columns, 1 outgoing FKs
- `rate_reports` — 6 columns, 2 outgoing FKs
- `rates` — 8 columns, 1 outgoing FKs
- `role_has_permissions` — 2 columns, 2 outgoing FKs
- `roles` — 5 columns, 0 outgoing FKs
- `service_daily_stats` — 9 columns, 1 outgoing FKs
- `service_items` — 9 columns, 1 outgoing FKs
- `service_plan_prices` — 7 columns, 1 outgoing FKs
- `service_subscription_extension_requests` — 10 columns, 0 outgoing FKs
- `service_subscription_new_requests` — 12 columns, 0 outgoing FKs
- `service_subscription_payments` — 6 columns, 2 outgoing FKs
- `service_subscription_plans` — 11 columns, 1 outgoing FKs
- `service_subscription_requests` — 25 columns, 8 outgoing FKs
- `service_subscriptions` — 10 columns, 3 outgoing FKs
- `service_working_days` — 4 columns, 1 outgoing FKs
- `services` — 20 columns, 3 outgoing FKs
- `sessions` — 5 columns, 0 outgoing FKs
- `store_daily_stats` — 8 columns, 1 outgoing FKs
- `store_plan_prices` — 7 columns, 1 outgoing FKs
- `store_subscription_extension_requests` — 10 columns, 0 outgoing FKs
- `store_subscription_new_requests` — 12 columns, 0 outgoing FKs
- `store_subscription_payments` — 6 columns, 2 outgoing FKs
- `store_subscription_plans` — 11 columns, 1 outgoing FKs
- `store_subscription_requests` — 21 columns, 7 outgoing FKs
- `store_subscriptions` — 10 columns, 3 outgoing FKs
- `stores` — 16 columns, 2 outgoing FKs
- `users` — 14 columns, 0 outgoing FKs
