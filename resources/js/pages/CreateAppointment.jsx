import { Navigate, useParams } from 'react-router-dom';

/**
 * Legacy route: /appointments/create/:residentId → new two-step add-appointment page.
 */
export default function CreateAppointment() {
    const { residentId } = useParams();
    if (residentId) {
        return <Navigate to={`/appointments/add/${residentId}`} replace />;
    }
    return <Navigate to="/appointments/add" replace />;
}
