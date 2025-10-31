export default function Login() {
    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50">
            <div className="max-w-md w-full bg-white rounded-lg shadow p-8">
                <h1 className="text-2xl font-bold text-gray-900 mb-6">Login</h1>
                <p className="text-gray-600 mb-4">
                    This is a placeholder. Please use the Filament admin login at /admin/login
                </p>
                <a
                    href="/admin/login"
                    className="block w-full text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Go to Admin Login
                </a>
            </div>
        </div>
    );
}

