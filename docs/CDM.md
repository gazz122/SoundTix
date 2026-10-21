# SoundTix CDM (Conceptual Data Model)

## Entities and Relationships

### Core Entities
- users (id, name, email, password, role, phone, avatar, email_verified_at, two_factor_secret, two_factor_recovery_codes, remember_token, deleted_at, timestamps)
- events (id, organizer_id, title, slug, description, venue_name, city, address, event_date, start_time, end_time, status, duration_type, total_days, open_gate, close_gate, banner_path, cancellation_reason, deleted_at, timestamps)
- event_days (id, event_id, day_number, date, open_gate, close_gate, timestamps)
- performers (id, name, genre, bio, photo_path, timestamps)
- event_performers (id, event_id, performer_id, timestamps)
- ticket_categories (id, event_id, name, access_type, event_day_id, description, price, total_quantity, available_quantity, max_per_order, timestamps)
- orders (id, customer_id, order_code, total_amount, admin_fee, grand_total, status, payment_status, expires_at, cancellation_reason, cancelled_at, deleted_at, timestamps)
- order_items (id, order_id, ticket_category_id, quantity, price_per_unit, subtotal, timestamps)
- payments (id, order_id, transaction_id, payment_type, gross_amount, payment_status, snap_token, redirect_url, raw_response, midtrans_transaction_id, expired_at, processed_at, timestamps)
- tickets (id, order_item_id, ticket_code, qr_payload, status, used_at, validated_by, timestamps)
- ticket_scans (id, ticket_id, event_day_id, scanned_at, gatekeeper_id, timestamps)
- refunds (id, order_id, payment_id, amount, bank_name, account_number, account_holder_name, proof_of_transfer, refund_key, status, failure_reason, gateway_response, processed_at, reason, notes, timestamps)
- payouts (id, event_id, organizer_id, gross_amount, platform_fee, refund_deductions, net_amount, bank_name, account_number, account_holder_name, status, transfer_proof, admin_notes, requested_at, processed_at, processed_by, timestamps)
- faqs (id, category_id, question, answer, is_published, sort_order, timestamps)
- faq_categories (id, name, slug, sort_order, timestamps)
- saved_events (id, user_id, event_id, timestamps)
- notifications (id, type, notifiable_type, notifiable_id, data, read_at, timestamps)

## Relationships
- User (organizer) hasMany Event, hasMany Payout (as organizer)
- Event belongsTo Organizer (User), hasMany EventDay, belongsToMany Performer, hasMany TicketCategory, hasMany Rundown, hasMany Payout, hasMany Refund (via orders)
- EventDay belongsTo Event, hasMany TicketCategory, hasMany TicketScan
- TicketCategory belongsTo Event, belongsTo EventDay (nullable), hasMany OrderItem
- Order belongsTo Customer (User), hasMany OrderItem, hasMany Payment, hasMany Refund
- OrderItem belongsTo Order, belongsTo TicketCategory, hasMany Ticket
- Payment belongsTo Order, hasMany Refund
- Ticket belongsTo OrderItem, belongsTo User (validated_by), hasMany TicketScan
- TicketScan belongsTo Ticket, belongsTo EventDay, belongsTo User (gatekeeper)
- Refund belongsTo Order, belongsTo Payment
- Payout belongsTo Event, belongsTo Organizer (User), belongsTo User (processed_by)
- FAQ belongsTo FAQCategory, FAQCategory hasMany FAQ
- SavedEvent belongsTo User, belongsTo Event

## Enums
- UserRole: admin, organizer, gatekeeper, customer
- EventStatus: draft, submitted, under_review, published, rejected, pending_cancellation, cancelled, completed
- EventDurationType: single_day, multi_day
- TicketCategoryAccessType: ALL_DAYS, SINGLE_DAY
- OrderStatus: pending, paid, expired, cancelled, failed
- PaymentStatus: pending, settlement, expire, failure
- PaymentType: bank_transfer, qris
- TicketStatus: active, used, cancelled
- RefundStatus: pending, completed, failed, refunded
- PayoutStatus: pending, processing, completed, failed
- FAQStatus: draft, published
