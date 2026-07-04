import { createContext, useContext, useEffect, useMemo, useState } from 'react';

const AssistantResidentContext = createContext(null);

export function AssistantResidentProvider({ children }) {
    const [pageResident, setPageResident] = useState(null); // { id, name } | null
    const value = useMemo(() => ({ pageResident, setPageResident }), [pageResident]);
    return <AssistantResidentContext.Provider value={value}>{children}</AssistantResidentContext.Provider>;
}

export function useAssistantResident() {
    const ctx = useContext(AssistantResidentContext);
    if (!ctx) {
        throw new Error('useAssistantResident must be used within AssistantResidentProvider');
    }
    return ctx;
}

// Resident-scoped pages call this to publish the resident they display.
// Pass null when no resident is loaded yet; clears on unmount.
export function useAssistantPageResident(resident) {
    const { setPageResident } = useAssistantResident();
    const id = resident?.id ?? null;
    const name = resident?.name ?? null;
    useEffect(() => {
        if (id && name) {
            setPageResident({ id, name });
        }
        return () => setPageResident(null);
    }, [id, name, setPageResident]);
}
