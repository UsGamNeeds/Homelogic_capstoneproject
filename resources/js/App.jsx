import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Residents from './pages/Residents';
import Appointments from './pages/Appointments';
import Vitals from './pages/Vitals';
import Medications from './pages/Medications';
import Reports from './pages/Reports';
import Login from './pages/Login';

function App() {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/" element={<Layout />}>
                <Route index element={<Navigate to="/dashboard" replace />} />
                <Route path="dashboard" element={<Dashboard />} />
                <Route path="residents" element={<Residents />} />
                <Route path="appointments" element={<Appointments />} />
                <Route path="vitals" element={<Vitals />} />
                <Route path="medications" element={<Medications />} />
                <Route path="reports" element={<Reports />} />
            </Route>
        </Routes>
    );
}

export default App;

