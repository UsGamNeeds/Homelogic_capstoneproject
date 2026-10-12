import React from 'react';
import ClinicalTriageBoard from '../../components/caregiver/ClinicalTriageBoard';

/**
 * Clinical is cross-resident triage: what needs doing across the house.
 * It used to render the same resident grid as the Residents hub; picking a
 * person now belongs in Residents, so the duplicate grid is gone.
 */
export default function ClinicalHubPage() {
    return <ClinicalTriageBoard />;
}
