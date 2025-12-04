import React, { useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Database } from 'lucide-react';
import api from '../../services/api';
import { useToastContext } from '../../contexts/ToastContext';

export default function DatabaseSettings() {
  const toast = useToastContext();
  const queryClient = useQueryClient();

  const { data: currentUser } = useQuery({
    queryKey: ['me'],
    queryFn: async () => {
      const response = await api.get('/me');
      return response.data?.data || response.data;
    },
  });

  const facilityId = currentUser?.facility_id;

  const { data: settings, isLoading } = useQuery({
    enabled: !!facilityId,
    queryKey: ['facility-settings', facilityId, 'database'],
    queryFn: async () => {
      const response = await api.get(`/facilities/${facilityId}/settings/database`);
      return response.data?.data || {};
    },
  });

  const defaultValues = useMemo(
    () => ({
      read_replica_enabled: !!settings?.read_replica_enabled?.value,
      query_logging_enabled: !!settings?.query_logging_enabled?.value,
      slow_query_threshold_ms: settings?.slow_query_threshold_ms?.value ?? 500,
    }),
    [settings]
  );

  const saveMutation = useMutation({
    mutationFn: async (values) => {
      const payload = {
        settings: {
          read_replica_enabled: { value: values.read_replica_enabled, type: 'boolean' },
          query_logging_enabled: { value: values.query_logging_enabled, type: 'boolean' },
          slow_query_threshold_ms: { value: values.slow_query_threshold_ms, type: 'integer' },
        },
      };

      const response = await api.put(`/facilities/${facilityId}/settings/database`, payload);
      return response.data;
    },
    onSuccess: () => {
      toast.showToast('Database settings updated successfully.', 'success');
      queryClient.invalidateQueries(['facility-settings', facilityId, 'database']);
    },
    onError: (error) => {
      toast.showToast(
        error.response?.data?.message || 'Failed to update database settings',
        'error'
      );
    },
  });

  const handleSubmit = (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const values = {
      read_replica_enabled: !!formData.get('read_replica_enabled'),
      query_logging_enabled: !!formData.get('query_logging_enabled'),
      slow_query_threshold_ms: parseInt(formData.get('slow_query_threshold_ms') || '0', 10),
    };
    saveMutation.mutate(values);
  };

  if (!facilityId) {
    return (
      <div className="p-6 bg-white rounded-xl shadow-sm">
        <p className="text-sm text-gray-600">
          Database settings are available once a facility is associated with your account.
        </p>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[200px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--theme-primary)]" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="bg-white rounded-xl shadow-sm p-6 flex items-center space-x-3">
        <div className="h-10 w-10 flex items-center justify-center rounded-lg bg-[var(--theme-primary)]/10 text-[var(--theme-primary)]">
          <Database className="w-5 h-5" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-gray-900">Database Settings</h1>
          <p className="text-sm text-gray-500">
            View connection information and tune performance-related options for this facility.
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm p-6 space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="space-y-2">
            <h2 className="text-sm font-semibold text-gray-900">Performance</h2>
            <label className="flex items-center space-x-2 text-sm text-gray-700">
              <input
                type="checkbox"
                name="read_replica_enabled"
                defaultChecked={defaultValues.read_replica_enabled}
                className="rounded border-gray-300 text-[var(--theme-primary)] focus:ring-[var(--theme-primary)]"
              />
              <span>Use read replica for heavy reporting workloads</span>
            </label>
            <label className="flex items-center space-x-2 text-sm text-gray-700">
              <input
                type="checkbox"
                name="query_logging_enabled"
                defaultChecked={defaultValues.query_logging_enabled}
                className="rounded border-gray-300 text-[var(--theme-primary)] focus:ring-[var(--theme-primary)]"
              />
              <span>Enable slow query logging</span>
            </label>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Slow Query Threshold (ms)
            </label>
            <input
              type="number"
              name="slow_query_threshold_ms"
              defaultValue={defaultValues.slow_query_threshold_ms}
              min={0}
              max={60000}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
            />
            <p className="mt-1 text-xs text-gray-400">
              Queries longer than this will be recorded for performance analysis.
            </p>
          </div>
        </div>

        <div className="pt-4 mt-4 border-t border-gray-200 flex justify-end">
          <button
            type="submit"
            disabled={saveMutation.isPending}
            className="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold rounded-lg bg-[var(--theme-primary)] text-white hover:bg-[var(--theme-primary-hover)] disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {saveMutation.isPending ? 'Saving...' : 'Save Changes'}
          </button>
        </div>
      </form>
    </div>
  );
}


