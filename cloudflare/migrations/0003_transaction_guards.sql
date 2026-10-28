CREATE TRIGGER prevent_negative_inventory
BEFORE UPDATE OF quantity_available ON inventory_balances
WHEN NEW.quantity_available < 0
BEGIN
    SELECT RAISE(ABORT, 'inventory balance cannot be negative');
END;

CREATE TRIGGER prevent_overpayment
BEFORE INSERT ON payments
WHEN (
    SELECT coalesce(sum(payments.amount), 0)
    FROM payments
    WHERE payments.order_id = NEW.order_id
) + NEW.amount > (
    SELECT orders.approved_total
    FROM orders
    WHERE orders.id = NEW.order_id
)
BEGIN
    SELECT RAISE(ABORT, 'recorded payments exceed the approved order total');
END;

CREATE TRIGGER prevent_overdelivery
BEFORE INSERT ON delivery_items
WHEN NEW.quantity_delivered <= 0
    OR NOT EXISTS (
        SELECT 1
        FROM deliveries
        JOIN order_items ON order_items.id = NEW.order_item_id
        WHERE deliveries.id = NEW.delivery_id
          AND deliveries.order_id = order_items.order_id
    )
    OR NEW.quantity_delivered + (
        SELECT coalesce(sum(delivery_items.quantity_delivered), 0)
        FROM delivery_items
        WHERE delivery_items.order_item_id = NEW.order_item_id
    ) > (
        SELECT order_items.quantity
        FROM order_items
        WHERE order_items.id = NEW.order_item_id
    )
BEGIN
    SELECT RAISE(ABORT, 'delivered quantity exceeds remaining order quantity');
END;
