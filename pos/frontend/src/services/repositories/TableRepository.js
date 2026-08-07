import apiClient from '@/services/apiClient';

export class TableRepository {
    async getAll(forceRefresh = false) {
        // Assume logic for cache handled here or in calling service
        const { data } = await apiClient.get('/tables');
        return data;
    }

    async getById(id, forceRefresh = false) {
        const { data } = await apiClient.get(`/tables/${id}`);
        return data;
    }

    async updateStatus(id, status) {
        const { data } = await apiClient.patch(`/tables/${id}/status`, { status });
        return data;
    }

    async getPendingOrders(tableId) {
        const { data } = await apiClient.get(`/tables/${tableId}/pending-orders`);
        return data;
    }

    async getTableFullContext(id) {
        const [table, pendingOrders] = await Promise.all([
            this.getById(id),
            this.getPendingOrders(id)
        ]);
        return { table, pendingOrders };
    }

    _invalidateTableCache(id) {
        // Implementation if needed
    }
}
