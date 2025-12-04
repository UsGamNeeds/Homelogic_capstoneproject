import React, { useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Mail, Send } from 'lucide-react';
import api from '../../services/api';
import { useToastContext } from '../../contexts/ToastContext';

export default function EmailSettings() {
  const toast = useToastContext();
  const queryClient = useQueryClient();

  // TODO: Replace with selected facility selector for super admins
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
    queryKey: ['facility-settings', facilityId, 'email'],
    queryFn: async () => {
      const response = await api.get(`/facilities/${facilityId}/settings/email`);
      return response.data?.data || {};
    },
  });

  const defaultValues = useMemo(
    () => ({
      mail_driver: settings?.mail_driver?.value || 'smtp',
      mail_host: settings?.mail_host?.value || '',
      mail_port: settings?.mail_port?.value || 587,
      mail_username: settings?.mail_username?.value || '',
      mail_password: '',
      mail_encryption: settings?.mail_encryption?.value || 'tls',
      mail_from_address: settings?.mail_from_address?.value || '',
      mail_from_name: settings?.mail_from_name?.value || '',
      test_recipient: settings?.test_recipient?.value || '',
    }),
    [settings]
  );

  const saveMutation = useMutation({
    mutationFn: async (values) => {
      const payload = {
        settings: {
          mail_driver: { value: values.mail_driver, type: 'string' },
          mail_host: { value: values.mail_host, type: 'string' },
          mail_port: { value: values.mail_port, type: 'integer' },
          mail_username: { value: values.mail_username, type: 'string' },
          // Only send password if filled, to avoid overwriting with empty
          ...(values.mail_password
            ? { mail_password: { value: values.mail_password, type: 'string' } }
            : {}),
          mail_encryption: { value: values.mail_encryption, type: 'string' },
          mail_from_address: { value: values.mail_from_address, type: 'string' },
          mail_from_name: { value: values.mail_from_name, type: 'string' },
          test_recipient: { value: values.test_recipient, type: 'string' },
        },
      };

      const response = await api.put(`/facilities/${facilityId}/settings/email`, payload);
      return response.data;
    },
    onSuccess: () => {
      toast.showToast('Email settings updated successfully.', 'success');
      queryClient.invalidateQueries(['facility-settings', facilityId, 'email']);
    },
    onError: (error) => {
      toast.showToast(
        error.response?.data?.message || 'Failed to update email settings',
        'error'
      );
    },
  });

  const handleSubmit = (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const values = Object.fromEntries(formData.entries());
    values.mail_port = values.mail_port ? parseInt(values.mail_port, 10) : null;
    saveMutation.mutate(values);
  };

  if (!facilityId) {
    return (
      <div className="p-6 bg-white rounded-xl shadow-sm">
        <p className="text-sm text-gray-600">
          Email settings are available once a facility is associated with your account.
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
          <Mail className="w-5 h-5" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-gray-900">Email Settings</h1>
          <p className="text-sm text-gray-500">
            Configure SMTP details used for notifications and alerts for this facility.
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm p-6 space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Driver</label>
            <select
              name="mail_driver"
              defaultValue={defaultValues.mail_driver}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
            >
              <option value="smtp">SMTP</option>
              <option value="sendmail">Sendmail</option>
              <option value="log">Log (development)</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Host</label>
            <input
              name="mail_host"
              defaultValue={defaultValues.mail_host}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
              placeholder="smtp.example.com"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Port</label>
            <input
              name="mail_port"
              type="number"
              defaultValue={defaultValues.mail_port}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
            <select
              name="mail_encryption"
              defaultValue={defaultValues.mail_encryption}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
            >
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
              <option value="null">None</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Username</label>
            <input
              name="mail_username"
              defaultValue={defaultValues.mail_username}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
              name="mail_password"
              type="password"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
              placeholder="••••••••"
            />
            <p className="mt-1 text-xs text-gray-400">
              Leave blank to keep the existing password.
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              From Email Address
            </label>
            <input
              name="mail_from_address"
              defaultValue={defaultValues.mail_from_address}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
              placeholder="noreply@example.com"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              From Name
            </label>
            <input
              name="mail_from_name"
              defaultValue={defaultValues.mail_from_name}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
              placeholder="Facility Name"
            />
          </div>
        </div>

        <div className="border-t border-gray-200 pt-4 mt-4 space-y-4">
          <h2 className="text-sm font-semibold text-gray-900">Test Email</h2>
          <div className="grid grid-cols-1 md:grid-cols-[2fr_1fr] gap-4 items-center">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Test Recipient Email
              </label>
              <input
                name="test_recipient"
                defaultValue={defaultValues.test_recipient}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--theme-primary)]"
                placeholder="you@example.com"
              />
            </div>
            <button
              type="submit"
              disabled={saveMutation.isPending}
              className="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg bg-[var(--theme-primary)] text-white hover:bg-[var(--theme-primary-hover)] disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {saveMutation.isPending ? (
                <>
                  <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2" />
                  Saving...
                </>
              ) : (
                <>
                  <Send className="w-4 h-4 mr-2" />
                  Save & Send Test Email
                </>
              )}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
}


