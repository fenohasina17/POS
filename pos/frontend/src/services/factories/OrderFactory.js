class OrderFactory {
    static createPendingOrder(tableId, userId, posId, sessionId, cart) {
        return {
            table_id: tableId,
            user_id: userId,
            point_of_sale_id: posId,
            cash_register_session_id: sessionId,
            discount_percentage: null,
            order_lines: cart.map(item => ({
                product_id: item.id,
                quantity: item.quantity,
                price: item.price
            }))
        };
    }

    static createAddProductsPayload(saleId, cart) {
        return {
            order_lines: cart.map(item => ({
                product_id: item.id,
                quantity: item.quantity,
                price: item.price
            }))
        };
    }
}

export const orderFactory = OrderFactory;
