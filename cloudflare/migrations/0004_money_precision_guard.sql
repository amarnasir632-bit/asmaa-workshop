DROP TRIGGER prevent_overpayment;

CREATE TRIGGER prevent_overpayment
BEFORE INSERT ON payments
WHEN CAST(ROUND((
    SELECT coalesce(sum(payments.amount), 0)
    FROM payments
    WHERE payments.order_id = NEW.order_id
) * 100) AS INTEGER)
    + CAST(ROUND(NEW.amount * 100) AS INTEGER)
    > CAST(ROUND((
        SELECT orders.approved_total
        FROM orders
        WHERE orders.id = NEW.order_id
    ) * 100) AS INTEGER)
BEGIN
    SELECT RAISE(ABORT, 'recorded payments exceed the approved order total');
END;
