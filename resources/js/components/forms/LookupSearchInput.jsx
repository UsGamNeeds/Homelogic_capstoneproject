import React, { useState, useRef, useEffect } from 'react';
import { Search, X, Plus } from 'lucide-react';
import api from '../../services/api';

/**
 * Searchable, growable lookup picker: search an existing shared catalog (GET
 * `${endpoint}?search=`), or add a new entry (POST `${endpoint}`) that becomes
 * immediately selectable by every other user. Used for the appointment form's
 * Consultant (/healthcare-providers), Hospital (/hospitals), and Specialty
 * (/specialties) fields.
 */
export default function LookupSearchInput({
    value,
    selectedName,
    onChange,
    endpoint,
    placeholder = 'Search...',
    entityLabel = 'entry',
    subtitleField = 'specialty',
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [isOpen, setIsOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [creating, setCreating] = useState(false);
    const containerRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (!isOpen) return;
        const timeout = setTimeout(async () => {
            setLoading(true);
            try {
                const response = await api.get(endpoint, { params: { search: query, per_page: 10 } });
                setResults(response.data?.data || []);
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, 300);
        return () => clearTimeout(timeout);
    }, [query, isOpen, endpoint]);

    const handleSelect = (item) => {
        onChange(item.id, item.name);
        setQuery('');
        setIsOpen(false);
    };

    const handleClear = () => {
        onChange(null, '');
        setQuery('');
    };

    const handleCreate = async () => {
        const name = query.trim();
        if (!name) return;
        setCreating(true);
        try {
            const response = await api.post(endpoint, { name });
            handleSelect(response.data);
        } catch {
            // Leave the dropdown open so the user can retry or adjust the name.
        } finally {
            setCreating(false);
        }
    };

    const trimmedQuery = query.trim();
    const hasExactMatch = results.some((r) => r.name?.toLowerCase() === trimmedQuery.toLowerCase());
    const showCreateOption = trimmedQuery.length > 0 && !hasExactMatch && !loading;

    return (
        <div className="relative" ref={containerRef}>
            <div className="relative">
                <input
                    type="text"
                    value={isOpen ? query : (selectedName || '')}
                    onChange={(e) => setQuery(e.target.value)}
                    onFocus={() => setIsOpen(true)}
                    placeholder={placeholder}
                    readOnly={!isOpen && !!value}
                    className="w-full pl-3 pr-16 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                />
                <div className="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                    {value && (
                        <button type="button" onClick={handleClear} className="p-1 text-gray-400 hover:text-gray-600" aria-label="Clear">
                            <X className="w-4 h-4" />
                        </button>
                    )}
                    <Search className="w-4 h-4 text-gray-400" />
                </div>
            </div>

            {isOpen && (
                <div className="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                    {loading ? (
                        <div className="p-3 text-sm text-gray-500">Searching...</div>
                    ) : (
                        <>
                            {results.length === 0 && !showCreateOption && (
                                <div className="p-3 text-sm text-gray-500">No {entityLabel}s found</div>
                            )}
                            {results.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => handleSelect(item)}
                                    className="w-full text-left px-3 py-2 hover:bg-gray-50 border-b border-gray-50 last:border-b-0"
                                >
                                    <p className="text-sm font-medium text-gray-900">{item.name}</p>
                                    {subtitleField && item[subtitleField] && (
                                        <p className="text-xs text-gray-500">{item[subtitleField]}</p>
                                    )}
                                </button>
                            ))}
                            {showCreateOption && (
                                <button
                                    type="button"
                                    onClick={handleCreate}
                                    disabled={creating}
                                    className="w-full flex items-center gap-1.5 text-left px-3 py-2 text-sm font-medium text-[var(--theme-primary)] hover:bg-[var(--theme-primary-bg)] disabled:opacity-50"
                                >
                                    <Plus className="w-3.5 h-3.5 flex-shrink-0" />
                                    {creating ? 'Adding...' : `Add "${trimmedQuery}" as new ${entityLabel}`}
                                </button>
                            )}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
