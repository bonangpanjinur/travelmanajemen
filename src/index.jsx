import React, { useState, useEffect, useMemo, useRef } from 'react';
import ReactDOM from 'react-dom/client';
import { BarChart, Bar, XAxis, YAxis, Tooltip, Legend, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { 
    Users, LayoutDashboard, FileText, ClipboardCheck, 
    UsersRound, Target, Package, BookUser, Wallet, Settings,
    LogOut, Menu, X, Bell, Search, Plus, Printer, Download, 
    Check, Edit, ChevronRight, CreditCard, DollarSign, CalendarX2,
    CheckCircle2, XCircle, Clock, MoreVertical, ChevronDown,
    Upload, ArrowRight, UserPlus, CalendarCheck, CalendarOff,
    FileDown, ExternalLink, Info, Building, Plane, Moon,
    AlertTriangle, Server, FileCog
} from 'lucide-react';

// === DATA DARI PHP (WP 'localize_script') ===
const { apiUrl, apiNonce, userRole, userName } = window.umrohData || {
    apiUrl: '/wp-json/umroh/v1',
    apiNonce: '',
    userRole: 'guest',
    userName: 'Tamu'
};

// Cek role admin
const isAdmin = userRole === 'administrator' || userRole === 'owner';

// === HOOK API UTAMA ===
// Hook kustom untuk interaksi API yang aman
const useApi = () => {
    // Fungsi fetch utama
    const apiFetch = async (endpoint, options = {}) => {
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': apiNonce, // Nonce untuk keamanan WP
            ...options.headers,
        };
        // Hapus Content-Type untuk FormData (upload file)
        if (options.body instanceof FormData) {
            delete headers['Content-Type'];
        }

        let response;
        try {
            response = await fetch(`${apiUrl}${endpoint}`, { ...options, headers });
        } catch (networkError) {
            console.error("Network error:", networkError);
            throw new Error("Koneksi ke server gagal. Cek internet Anda.");
        }

        // Tangani error otentikasi
        if (response.status === 401 || response.status === 403) {
            console.error("Akses ditolak. Sesi Anda mungkin telah habis. Silakan refresh halaman.");
            throw new Error("Akses ditolak. Sesi Anda mungkin telah habis. Silakan refresh halaman.");
        }
        
        // Tangani respons kosong (misal: 204 No Content)
        if (response.status === 204) {
            return null;
        }
        
        const contentType = response.headers.get("content-type");
        
        // Tangani respons JSON
        if (contentType && contentType.indexOf("application/json") !== -1) {
             const data = await response.json();
             if (!response.ok) {
                 // Ambil pesan error dari JSON
                 const errorMessage = data.message || 'Terjadi kesalahan pada server';
                 console.error("API Error (JSON):", errorMessage, "Endpoint:", endpoint, "Response:", data);
                 throw new Error(errorMessage);
             }
             return data; // Sukses
        } else {
             // Tangani respons Teks (untuk CSV/Print)
             const textData = await response.text();
             if (!response.ok) {
                 console.error("API Error (Non-JSON):", textData, "Endpoint:", endpoint);
                 throw new Error(textData || "Terjadi kesalahan server");
             }
             return textData; // Sukses (CSV)
        }
    };

    // Fungsi khusus upload file
    const uploadFile = async (file) => {
        const formData = new FormData();
        formData.append('file', file);
        
        const response = await fetch(`${apiUrl}/upload`, {
             method: 'POST',
             headers: { 'X-WP-Nonce': apiNonce }, // Hanya butuh Nonce
             body: formData,
        });
        
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || "Gagal upload file.");
        }
        
        const data = await response.json();
        return data.url; // Mengembalikan URL file yang di-upload
    };

    return { apiFetch, uploadFile };
};

// === KOMPONEN UI DASAR ===

// Loader utama
const FullScreenLoader = ({ text = "Memuat..." }) => (
    <div className="flex items-center justify-center h-screen bg-slate-50">
        <div className="flex flex-col items-center">
            <svg className="w-12 h-12 text-indigo-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p className="mt-4 text-lg font-medium text-gray-700">{text}</p>
        </div>
    </div>
);

// Modal
const Modal = ({ show, onClose, title, children, size = 'xl' }) => {
    if (!show) return null;
    const sizeClasses = { sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl', '2xl': 'max-w-2xl', '4xl': 'max-w-4xl' };
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm" onClick={onClose}>
            <div className={`bg-white rounded-2xl shadow-2xl w-full ${sizeClasses[size]} m-4 transform transition-all duration-300 ease-out`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between p-5 border-b border-gray-100">
                    <h3 className="text-xl font-semibold text-gray-800">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-100">
                        <X className="w-6 h-6" />
                    </button>
                </div>
                <div className="p-6 max-h-[70vh] overflow-y-auto">
                    {children}
                </div>
            </div>
        </div>
    );
};

// Form Helper
const Input = React.forwardRef((props, ref) => (
    <input {...props} ref={ref} className="w-full px-4 py-2.5 mt-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50" />
));
const Select = React.forwardRef((props, ref) => (
    <select {...props} ref={ref} className="w-full px-4 py-2.5 mt-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50">
        {props.children}
    </select>
));
const Label = (props) => (
    <label {...props} className="block text-sm font-medium text-gray-700">{props.children}</label>
);
const Button = ({ children, onClick, type = 'button', variant = 'primary', disabled = false, icon: Icon, className = "" }) => {
    const base = "flex items-center justify-center px-4 py-2.5 rounded-xl font-medium transition-all duration-200 transform active:scale-95 disabled:opacity-60";
    const variants = {
        primary: "bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg shadow-indigo-200",
        secondary: "bg-white text-gray-700 border border-gray-200 hover:bg-gray-50",
        danger: "bg-rose-50 text-rose-600 hover:bg-rose-100",
        ghost: "text-gray-500 hover:bg-gray-100"
    };
    return (
        <button type={type} onClick={onClick} disabled={disabled} className={`${base} ${variants[variant]} ${className}`}>
            {Icon && <Icon size={18} className="mr-2" />}
            {children}
        </button>
    );
};
const Card = ({ children, className = "" }) => (
    <div className={`bg-white rounded-2xl shadow-sm border border-gray-100 ${className}`}>{children}</div>
);
const Badge = ({ children, color = "blue", className = "" }) => {
    const colors = {
        blue: "bg-blue-50 text-blue-700",
        green: "bg-emerald-50 text-emerald-700",
        yellow: "bg-amber-50 text-amber-700",
        red: "bg-rose-50 text-rose-700",
        gray: "bg-gray-50 text-gray-600"
    };
    return ( <span className={`px-3 py-1 rounded-full text-xs font-semibold ${colors[color] || colors.gray} ${className}`}>{children}</span> );
};
const Alert = ({ message, type = "error" }) => {
    if (!message) return null;
    const colors = {
        error: "bg-red-50 text-red-700",
        success: "bg-green-50 text-green-700"
    };
    return (
        <div className={`p-4 mb-4 rounded-lg ${colors[type]}`}>
            <div className="flex items-center">
                <AlertTriangle size={20} className="mr-3" />
                <span className="font-medium">{message}</span>
            </div>
        </div>
    );
};

// === LAYOUT UTAMA (HYBRID) ===
const DashboardLayout = () => {
    const [view, setView] = useState('dashboard');
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    // Inject Tailwind & Font (Solusi UI Hancur)
    useEffect(() => {
        if (!document.getElementById('tailwind-cdn')) {
            const script = document.createElement('script');
            script.id = 'tailwind-cdn';
            script.src = "https://cdn.tailwindcss.com";
            document.head.appendChild(script);
        }
        if (!document.getElementById('jakarta-font')) {
            const link = document.createElement('link');
            link.id = 'jakarta-font';
            link.href = "https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap";
            link.rel = "stylesheet";
            document.head.appendChild(link);
        }
        // CSS untuk menyembunyikan admin WP
        const style = document.createElement('style');
        style.innerHTML = `
            #wpcontent { padding-left: 0; }
            #adminmenumain, #wpfooter, #wpadminbar { display: none; }
            html.wp-toolbar { padding-top: 0; }
            .auto-fold #wpcontent { margin-left: 0; }
        `;
        document.head.appendChild(style);
    }, []);

    const menuItems = [
        { id: 'dashboard', label: 'Overview', icon: LayoutDashboard },
        { id: 'manifest', label: 'Jemaah', icon: Users },
        { id: 'tasks', label: 'Tugas', icon: ClipboardCheck },
        { id: 'leads', label: 'Leads', icon: Target },
        { id: 'packages', label: 'Paket', icon: Package },
        { id: 'hr', label: 'HRD', icon: BookUser },
        { id: 'users', label: 'Karyawan', icon: UsersRound },
        { id: 'finance', label: 'Keuangan', icon: Wallet, adminOnly: true },
        { id: 'logs', label: 'Aktivitas', icon: Settings, adminOnly: true },
    ];

    const renderView = () => {
        switch (view) {
            case 'dashboard': return <DashboardView />;
            case 'manifest': return <ManifestView />;
            case 'tasks': return <TasksView />;
            case 'leads': return <LeadsView />;
            case 'packages': return <PackagesView />;
            case 'hr': return <HRView />;
            case 'users': return <UsersView />;
            case 'finance': return <FinanceView />;
            case 'logs': return <LogsView />;
            default: return <DashboardView />;
        }
    };

    return (
        <div className="min-h-screen bg-slate-50" style={{ fontFamily: "'Plus Jakarta Sans', sans-serif" }}>
            {/* Sidebar Desktop */}
            <aside className="hidden lg:flex flex-col w-72 bg-white border-r border-gray-100 fixed inset-y-0 z-50">
                <div className="flex items-center h-20 px-8 border-b border-gray-50">
                    <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center mr-3">
                        <span className="text-white font-bold text-lg">T</span>
                    </div>
                    <span className="text-xl font-bold text-gray-900">TravelPanel</span>
                </div>

                <div className="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                    <div className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4 px-4">Menu Utama</div>
                    {menuItems.map(item => {
                        if (item.adminOnly && !isAdmin) return null;
                        const isActive = view === item.id;
                        return (
                            <button key={item.id} onClick={() => setView(item.id)}
                                className={`flex items-center w-full px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 ${
                                    isActive ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'
                                }`}>
                                <item.icon size={20} className={`mr-3 ${isActive ? 'text-indigo-600' : 'text-gray-400'}`} />
                                {item.label}
                                {isActive && <ChevronRight size={16} className="ml-auto text-indigo-400" />}
                            </button>
                        );
                    })}
                </div>

                <div className="p-4 border-t border-gray-50">
                    <div className="bg-slate-50 rounded-xl p-4 flex items-center">
                        <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                            {userName.charAt(0)}
                        </div>
                        <div className="ml-3 flex-1 min-w-0">
                            <p className="text-sm font-bold text-gray-900 truncate">{userName}</p>
                            <p className="text-xs text-gray-500 capitalize truncate">{userRole}</p>
                        </div>
                        <a href={window.location.origin + '/wp-login.php?action=logout'} className="text-gray-400 hover:text-rose-500 transition-colors">
                            <LogOut size={18} />
                        </a>
                    </div>
                </div>
            </aside>

            {/* Header & Content */}
            <div className="lg:ml-72 min-h-screen flex flex-col">
                {/* Mobile Header */}
                <header className="bg-white/80 backdrop-blur-md sticky top-0 z-40 border-b border-gray-100 px-6 py-4 flex items-center justify-between lg:hidden">
                    <div className="flex items-center">
                        <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center mr-3">
                            <span className="text-white font-bold">T</span>
                        </div>
                        <span className="font-bold text-gray-900">TravelPanel</span>
                    </div>
                    <button onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)} className="p-2 bg-gray-50 rounded-lg">
                        {isMobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
                    </button>
                </header>

                {/* Mobile Menu Dropdown */}
                {isMobileMenuOpen && (
                    <div className="lg:hidden bg-white border-b border-gray-100 absolute top-16 left-0 right-0 z-50 px-4 py-4 shadow-xl">
                        <div className="grid grid-cols-3 gap-2">
                            {menuItems.map(item => {
                                if (item.adminOnly && !isAdmin) return null;
                                return (
                                    <button
                                        key={item.id}
                                        onClick={() => { setView(item.id); setIsMobileMenuOpen(false); }}
                                        className={`flex flex-col items-center p-4 rounded-xl ${view === item.id ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-50 text-gray-600'}`}>
                                        <item.icon size={24} className="mb-2" />
                                        <span className="text-xs font-medium">{item.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Main Content Area */}
                <main className="flex-1 p-6 lg:p-10 max-w-7xl mx-auto w-full">
                    {renderView()}
                </main>
            </div>
        </div>
    );
};

// --- 1. DASHBOARD VIEW ---
const DashboardView = () => {
    const { apiFetch } = useApi();
    const [stats, setStats] = useState(null);
    useEffect(() => {
        apiFetch('/dashboard/stats').then(setStats).catch(err => console.error(err));
    }, []);

    if (!stats) return <div className="p-10 text-center text-gray-500 animate-pulse">Sedang memuat data dashboard...</div>;
    
    const kpiData = [
        { title: 'Total Omset (Bulan Ini)', value: `Rp ${Number(stats?.omset_month || 0).toLocaleString('id-ID')}`, icon: DollarSign, color: 'text-emerald-600', bg: 'bg-emerald-50' },
        { title: 'Jemaah Baru (Bulan Ini)', value: stats?.new_pilgrims_month || 0, icon: Users, color: 'text-blue-600', bg: 'bg-blue-50' },
        { title: 'Total Kasbon Aktif', value: `Rp ${Number(stats?.total_kasbon || 0).toLocaleString('id-ID')}`, icon: CreditCard, color: 'text-amber-600', bg: 'bg-amber-50' },
        { title: 'Tugas Belum Selesai', value: stats?.pending_tasks || 0, icon: ClipboardCheck, color: 'text-rose-600', bg: 'bg-rose-50' },
    ];
    
    return (
        <div className="space-y-8">
            <div>
                <h1 className="text-3xl font-bold text-gray-900 tracking-tight">Dashboard Overview</h1>
                <p className="text-gray-500 mt-1">Ringkasan performa bisnis travel Anda bulan ini.</p>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                {kpiData.map((item) => (
                    <Card key={item.title} className="p-6 flex items-start space-x-4 hover:shadow-md transition-shadow">
                        <div className={`p-3 rounded-xl ${item.bg} ${item.color}`}>
                            <item.icon size={24} />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-gray-500">{item.title}</p>
                            <h4 className="text-2xl font-bold text-gray-900 mt-1">{item.value}</h4>
                        </div>
                    </Card>
                ))}
            </div>
            <Card className="p-6 lg:p-8">
                <h3 className="text-lg font-bold text-gray-900 mb-6">Tren Omset Tahunan</h3>
                <div className="h-80 w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={stats?.omset_12_months}>
                            <XAxis dataKey="month" axisLine={false} tickLine={false} tick={{fill: '#94a3b8', fontSize: 12}} />
                            <YAxis axisLine={false} tickLine={false} tick={{fill: '#94a3b8', fontSize: 12}} tickFormatter={(val) => `${val/1000000}jt`} />
                            <Tooltip cursor={{fill: '#f1f5f9'}} contentStyle={{borderRadius: '12px', border: 'none', boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1)'}} />
                            <Bar dataKey="omset" fill="#4f46e5" radius={[4, 4, 0, 0]} barSize={40} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </Card>
        </div>
    );
};

// --- 2. MANIFEST VIEW ---
const ManifestView = () => {
    const { apiFetch } = useApi();
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [selectedJemaah, setSelectedJemaah] = useState(null);

    const fetchData = async () => {
        try { setLoading(true); const res = await apiFetch('/manifest'); setData(res); } 
        catch (err) { setError(err.message); } 
        finally { setLoading(false); }
    };
    useEffect(() => { fetchData(); }, []);

    const handleOpenDetail = (jemaah) => { setSelectedJemaah(jemaah); setShowModal(true); };
    const handleCloseModal = () => { setShowModal(false); setSelectedJemaah(null); fetchData(); };
    
    const handleExport = async () => {
        setLoading(true);
        try {
            const csv_data = await apiFetch('/export/manifest'); 
            const blob = new Blob([csv_data], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none'; a.href = url;
            a.download = `manifest_jemaah_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a); a.click();
            window.URL.revokeObjectURL(url); document.body.removeChild(a);
        } catch (error) { setError("Error export: " + error.message); } 
        finally { setLoading(false); }
    };
    
    const handlePrint = (jemaahId) => {
        const printUrl = `${apiUrl}/print/invoice?id=${jemaahId}&_wpnonce=${apiNonce}`;
        window.open(printUrl, '_blank');
    };

    if (loading && data.length === 0) return <FullScreenLoader text="Memuat Manifest..." />;
    
    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Manifest Jemaah</h1>
                    <p className="text-gray-500 text-sm">Kelola data keberangkatan dan dokumen jemaah.</p>
                </div>
                <div className="flex space-x-2">
                    <Button onClick={handleExport} variant="secondary" icon={Download} disabled={loading}>Export CSV</Button>
                    <Button onClick={() => handleOpenDetail(null)} icon={Plus}>Tambah Jemaah</Button>
                </div>
            </div>
            
            <Alert message={error} type="error" />

            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jemaah</th>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Paket</th>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pembayaran</th>
                                <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Visa</th>
                                <th className="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {data.length === 0 && !loading ? (
                                <tr><td colSpan="5" className="px-6 py-8 text-center text-gray-500">Belum ada data jemaah.</td></tr>
                            ) : data.map((item) => (
                                <tr key={item.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center">
                                            <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm flex-shrink-0">
                                                {item.full_name.charAt(0)}
                                            </div>
                                            <div className="ml-3">
                                                <div className="text-sm font-bold text-gray-900">{item.full_name}</div>
                                                <div className="text-xs text-gray-500">{item.passport_number || 'No Paspor -'}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="text-sm text-gray-900">{item.package_name || 'Umum'}</div>
                                        <div className="text-xs text-gray-500">Rp {Number(item.final_price).toLocaleString('id-ID')}</div>
                                    </td>
                                    <td className="px-6 py-4">
                                        <Badge color={item.payment_status === 'Lunas' ? 'green' : (item.payment_status === 'DP' ? 'blue' : 'yellow')}>{item.payment_status}</Badge>
                                    </td>
                                    <td className="px-6 py-4">
                                        <Badge color={item.visa_status === 'Issued' ? 'green' : 'gray'}>{item.visa_status}</Badge>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end space-x-2">
                                            <button onClick={() => handlePrint(item.id)} className="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" title="Cetak Kwitansi">
                                                <Printer size={18} />
                                            </button>
                                            <button onClick={() => handleOpenDetail(item)} className="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" title="Detail / Edit">
                                                <Edit size={18} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <JemaahDetailModal show={showModal} onClose={handleCloseModal} jemaah={selectedJemaah} />
        </div>
    );
};

// --- Modal Detail Jemaah (Form Kompleks) ---
const JemaahDetailModal = ({ show, onClose, jemaah }) => {
    const { apiFetch, uploadFile } = useApi();
    const [packages, setPackages] = useState([]);
    const [activeTab, setActiveTab] = useState('detail');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [form, setForm] = useState({});
    
    // Tab Pembayaran
    const [payments, setPayments] = useState([]);
    const [paymentAmount, setPaymentAmount] = useState(0);
    const [paymentProof, setPaymentProof] = useState(null);
    const [paymentLoading, setPaymentLoading] = useState(false);
    const fileInputRef = useRef(null); // Ref untuk reset file input

    // Fetch paket saat modal load
    useEffect(() => {
        if (show) {
            apiFetch('/packages').then(setPackages).catch(err => console.error("Gagal load paket:", err));
        }
    }, [show]);

    // Isi form jika ini mode Edit (jemaah not null)
    useEffect(() => {
        if (show) {
            if (jemaah) {
                setForm({
                    full_name: jemaah.full_name || '',
                    passport_number: jemaah.passport_number || '',
                    passport_expiry: jemaah.passport_expiry || '',
                    package_id: jemaah.package_id || '',
                    final_price: jemaah.final_price || 0,
                    visa_status: jemaah.visa_status || 'Pending',
                    equipment_status: jemaah.equipment_status || 'Belum Diambil',
                    payment_status: jemaah.payment_status || 'Unpaid',
                    status: jemaah.status || 'Active',
                });
                fetchPayments();
            } else {
                // Reset form jika mode Create
                setForm({
                    full_name: '', passport_number: '', passport_expiry: '',
                    package_id: '', final_price: 0, visa_status: 'Pending',
                    equipment_status: 'Belum Diambil', payment_status: 'Unpaid', status: 'Active'
                });
                setPayments([]);
            }
            setActiveTab('detail'); // Selalu reset ke tab detail
            setError('');
        }
    }, [jemaah, show]);
    
    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });
    
    // Fetch riwayat pembayaran
    const fetchPayments = async () => {
        if (!jemaah) return;
        setPaymentLoading(true);
        apiFetch(`/manifest/${jemaah.id}/payments`)
            .then(setPayments)
            .catch(err => setError("Gagal load riwayat pembayaran: " + err.message))
            .finally(() => setPaymentLoading(false));
    };

    // Handle ganti paket -> auto-fill harga
    const handlePackageChange = (e) => {
        const pkgId = e.target.value;
        const pkg = packages.find(p => p.id == pkgId);
        handleChange(e); // set package_id
        if (pkg) {
            const prices = JSON.parse(pkg.price_details || '{}');
            // Ambil harga Quad sebagai default jika ada
            const defaultPrice = prices.Quad || prices.Triple || prices.Double || 0;
            setForm(f => ({ ...f, final_price: defaultPrice }));
        } else {
            setForm(f => ({ ...f, final_price: 0 }));
        }
    };
    
    // Handle Simpan (Create / Update)
    const handleSubmit = async (e) => {
        e.preventDefault(); setLoading(true); setError('');
        try {
            if (jemaah) {
                await apiFetch(`/manifest/${jemaah.id}`, { method: 'PUT', body: JSON.stringify(form) });
            } else {
                await apiFetch('/manifest', { method: 'POST', body: JSON.stringify(form) });
            }
            onClose();
        } catch (err) { setError(err.message); } 
        finally { setLoading(false); }
    };
    
    // Handle Input Cicilan
    const handleAddPayment = async () => {
        if (paymentAmount <= 0) return setError("Nominal pembayaran harus diisi");
        setPaymentLoading(true); setError('');
        let proofUrl = '';
        try {
            if (paymentProof) proofUrl = await uploadFile(paymentProof);
            await apiFetch(`/manifest/${jemaah.id}/payment`, {
                method: 'POST',
                body: JSON.stringify({
                    amount: paymentAmount, date: new Date().toISOString().split('T')[0],
                    method: 'Transfer', proof_url: proofUrl, notes: 'Pembayaran via dashboard'
                })
            });
            setPaymentAmount(0); setPaymentProof(null);
            if(fileInputRef.current) fileInputRef.current.value = null; // Reset file input
            fetchPayments(); // Refresh list cicilan
        } catch (err) { setError("Gagal menambah pembayaran: " + err.message); } 
        finally { setPaymentLoading(false); }
    };
    
    // Handle Refund
    const handleRefund = async () => {
        if (!window.confirm("Apakah Anda yakin ingin memproses refund jemaah ini? Aksi ini akan mengubah status jemaah menjadi 'Refund' dan mencatat pengeluaran di Keuangan Kantor.")) return;
        setLoading(true); setError('');
        try {
             await apiFetch(`/manifest/${jemaah.id}/refund`, { method: 'POST', body: JSON.stringify({ notes: "Proses refund/pembatalan jemaah" }) });
            onClose();
        } catch (err) { setError("Gagal proses refund: " + err.message); } 
        finally { setLoading(false); }
    };

    return (
        <Modal show={show} onClose={onClose} title={jemaah ? "Detail Jemaah" : "Input Jemaah Baru"} size="4xl">
            <Alert message={error} type="error" />
            
            <div className="border-b border-gray-200">
                <nav className="-mb-px flex space-x-6">
                    <button onClick={() => setActiveTab('detail')}
                        className={`py-3 px-1 border-b-2 font-medium text-sm ${activeTab === 'detail' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}`}>
                        Detail & Status
                    </button>
                    {jemaah && (
                         <button onClick={() => setActiveTab('payment')}
                            className={`py-3 px-1 border-b-2 font-medium text-sm ${activeTab === 'payment' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}`}>
                            Pembayaran & Cicilan
                         </button>
                    )}
                </nav>
            </div>
            
            {activeTab === 'detail' && (
                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 gap-6 mt-6 md:grid-cols-3">
                        <div className="md:col-span-1 space-y-4">
                            <h4 className="font-semibold text-gray-800">Data Diri</h4>
                            <div><Label>Nama Lengkap</Label><Input name="full_name" value={form.full_name || ''} onChange={handleChange} required /></div>
                            <div><Label>No. Paspor</Label><Input name="passport_number" value={form.passport_number || ''} onChange={handleChange} /></div>
                            <div><Label>Tgl Habis Paspor</Label><Input name="passport_expiry" type="date" value={form.passport_expiry || ''} onChange={handleChange} /></div>
                             <div><Label>Status Jemaah</Label><Select name="status" value={form.status || 'Active'} onChange={handleChange}>
                                <option value="Active">Aktif</option><option value="Batal">Batal</option><option value="Refund">Refund</option>
                             </Select></div>
                        </div>
                        
                        <div className="md:col-span-1 space-y-4">
                            <h4 className="font-semibold text-gray-800">Paket & Harga</h4>
                            <div><Label>Pilih Paket</Label><Select name="package_id" value={form.package_id || ''} onChange={handlePackageChange} required>
                                <option value="">-- Pilih Paket --</option>
                                {packages.map(pkg => (<option key={pkg.id} value={pkg.id}>{pkg.title}</option>))}
                            </Select></div>
                            <div><Label>Harga Final (Rp)</Label><Input name="final_price" type="number" value={form.final_price || 0} onChange={handleChange} required /></div>
                        </div>
                        
                        <div className="md:col-span-1 space-y-4">
                            <h4 className="font-semibold text-gray-800">Status Operasional</h4>
                             <div><Label>Status Pembayaran</Label><Select name="payment_status" value={form.payment_status || 'Unpaid'} onChange={handleChange}>
                                <option value="Unpaid">Unpaid</option><option value="DP">DP</option><option value="Lunas">Lunas</option><option value="Refund">Refund</option>
                             </Select></div>
                             <div><Label>Status Visa</Label><Select name="visa_status" value={form.visa_status || 'Pending'} onChange={handleChange}>
                                <option value="Pending">Pending</option><option value="Submitted">Submitted</option><option value="Issued">Issued</option><option value="Rejected">Rejected</option>
                             </Select></div>
                            <div><Label>Status Perlengkapan</Label><Select name="equipment_status" value={form.equipment_status || 'Belum Diambil'} onChange={handleChange}>
                                <option value="Belum Diambil">Belum Diambil</option><option value="Sudah Diambil">Sudah Diambil</option>
                            </Select></div>
                        </div>
                    </div>
                    
                    <div className="flex justify-between items-center pt-6 mt-6 border-t">
                        <div>
                            {jemaah && form.status === 'Active' && (
                                 <Button onClick={handleRefund} variant="danger" icon={CalendarX2} disabled={loading}>Proses Batal/Refund</Button>
                            )}
                        </div>
                        <div className="flex space-x-3"><Button type="submit" variant="primary" icon={Check} disabled={loading}>{loading ? 'Menyimpan...' : 'Simpan Data'}</Button></div>
                    </div>
                </form>
            )}
            
            {activeTab === 'payment' && jemaah && (
                <div className="mt-6">
                    <div className="grid grid-cols-1 gap-4 p-4 mb-6 bg-gray-50 md:grid-cols-3 rounded-lg border">
                        <div><Label>Nominal (Rp)</Label><Input type="number" value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} /></div>
                         <div><Label>Upload Bukti (Opsional)</Label><Input ref={fileInputRef} id="paymentProofFile" type="file" onChange={(e) => setPaymentProof(e.target.files[0])} /></div>
                        <div className="self-end"><Button onClick={handleAddPayment} icon={Plus} disabled={paymentLoading}>{paymentLoading ? 'Menyimpan...' : 'Tambah Pembayaran'}</Button></div>
                    </div>
                    
                    <h4 className="font-semibold text-gray-800 mb-3">Riwayat Pembayaran</h4>
                    <div className="max-h-64 overflow-y-auto border rounded-lg">
                        <table className="min-w-full"><tbody className="divide-y">
                            {paymentLoading && <tr><td colSpan="4" className="p-4 text-center text-gray-500">Memuat...</td></tr>}
                            {!paymentLoading && payments.length > 0 ? payments.map(p => (
                                <tr key={p.id}>
                                    <td className="px-4 py-2 text-sm text-gray-700">{new Date(p.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                                    <td className="px-4 py-2 text-sm font-medium text-gray-900">Rp {Number(p.amount).toLocaleString('id-ID')}</td>
                                    <td className="px-4 py-2 text-sm text-gray-500">{p.method}</td>
                                    <td className="px-4 py-2 text-sm">{p.proof_url ? (<a href={p.proof_url} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:underline">Lihat Bukti</a>) : '-'}</td>
                                </tr>
                            )) : (
                                !paymentLoading && <tr><td className="px-4 py-3 text-sm text-gray-500 text-center" colSpan="4">Belum ada riwayat pembayaran.</td></tr>
                            )}
                        </tbody></table>
                    </div>
                </div>
            )}
        </Modal>
    );
};

// --- 3. TASKS VIEW ---
const TasksView = () => {
    const { apiFetch } = useApi();
    const [tasks, setTasks] = useState([]);
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [title, setTitle] = useState('');
    const [assignedTo, setAssignedTo] = useState('');
    const [error, setError] = useState('');

    const fetchData = async () => {
        setLoading(true); setError('');
        try {
            const [taskData, userData] = await Promise.all([
                apiFetch('/tasks'),
                apiFetch('/users')
            ]);
            setTasks(taskData);
            setUsers(userData);
        } catch (err) { setError(err.message); }
        finally { setLoading(false); }
    };
    useEffect(() => { fetchData(); }, []);
    
    const handleSubmit = async (e) => {
        e.preventDefault(); setError('');
        try {
            await apiFetch('/tasks', { method: 'POST', body: JSON.stringify({ title, assigned_to: assignedTo }) });
            setShowModal(false); setTitle(''); setAssignedTo('');
            fetchData();
        } catch (err) { setError(err.message); }
    };

    const handleToggleTask = async (task) => {
        setError('');
        try {
            const newStatus = task.status === 'Pending' ? 'Done' : 'Pending';
            await apiFetch(`/tasks/${task.id}`, { method: 'PUT', body: JSON.stringify({ status: newStatus }) });
            fetchData();
        } catch (err) { setError(err.message); }
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div><h1 className="text-2xl font-bold text-gray-900">Manajemen Tugas</h1><p className="text-gray-500 text-sm">Buat, assign, dan lacak tugas harian karyawan.</p></div>
                <Button onClick={() => setShowModal(true)} icon={Plus}>Tugas Baru</Button>
            </div>
            <Alert message={error} type="error" />
            <Card>
                <div className="divide-y divide-gray-100">
                    {loading && <div className="p-4 text-center text-gray-500">Memuat tugas...</div>}
                    {!loading && tasks.length === 0 && <div className="p-4 text-center text-gray-500">Belum ada tugas.</div>}
                    {tasks.map(task => (
                        <div key={task.id} className="flex items-center p-4 hover:bg-gray-50">
                            <button onClick={() => handleToggleTask(task)}>
                                {task.status === 'Done' ? <CheckCircle2 className="w-6 h-6 text-green-500" /> : <Clock className="w-6 h-6 text-gray-400" />}
                            </button>
                            <div className="ml-4 flex-1">
                                <p className={`font-medium ${task.status === 'Done' ? 'text-gray-500 line-through' : 'text-gray-900'}`}>{task.title}</p>
                                <span className="text-xs text-gray-500">
                                    Oleh: {task.assigner_name} | Untuk: {task.assignee_name}
                                </span>
                            </div>
                            <Badge color={task.status === 'Done' ? 'green' : 'gray'}>{task.status}</Badge>
                        </div>
                    ))}
                </div>
            </Card>
            <Modal show={showModal} onClose={() => setShowModal(false)} title="Buat Tugas Baru" size="md">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Alert message={error} type="error" />
                    <div><Label>Judul Tugas</Label><Input value={title} onChange={(e) => setTitle(e.target.value)} required /></div>
                    <div><Label>Assign ke Karyawan</Label><Select value={assignedTo} onChange={(e) => setAssignedTo(e.target.value)} required>
                        <option value="">-- Pilih Karyawan --</option>
                        {users.map(user => (<option key={user.id} value={user.id}>{user.name}</option>))}
                    </Select></div>
                    <Button type="submit" icon={Plus}>Buat Tugas</Button>
                </form>
            </Modal>
        </div>
    );
};

// --- 4. LEADS VIEW ---
const LeadsView = () => {
    const { apiFetch } = useApi();
    const [leads, setLeads] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [form, setForm] = useState({ name: '', phone: '', source: 'IG', status: 'Warm', notes: '' });
    const [error, setError] = useState('');

    const fetchData = async () => {
        setLoading(true); setError('');
        apiFetch('/leads').then(setLeads).catch(err => setError(err.message)).finally(() => setLoading(false));
    };
    useEffect(() => { fetchData(); }, []);
    
    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault(); setError('');
        try {
            await apiFetch('/leads', { method: 'POST', body: JSON.stringify(form) });
            setShowModal(false); setForm({ name: '', phone: '', source: 'IG', status: 'Warm', notes: '' });
            fetchData();
        } catch (err) { setError(err.message); }
    };
    
    const statusMap = { 'Hot': 'red', 'Warm': 'yellow', 'Cold': 'blue', 'Converted': 'green' };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div><h1 className="text-2xl font-bold text-gray-900">Calon Jemaah (Leads)</h1><p className="text-gray-500 text-sm">Database prospek dan sumber leads.</p></div>
                <Button onClick={() => setShowModal(true)} icon={UserPlus}>Lead Baru</Button>
            </div>
            <Alert message={error} type="error" />
            <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full">
                <thead className="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Kontak</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Sumber</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {loading && <tr><td colSpan="4" className="p-4 text-center text-gray-500">Memuat leads...</td></tr>}
                    {!loading && leads.length === 0 && <tr><td colSpan="4" className="p-4 text-center text-gray-500">Belum ada leads.</td></tr>}
                    {leads.map(lead => (
                        <tr key={lead.id} className="hover:bg-gray-50">
                            <td className="px-6 py-4 text-sm font-medium text-gray-900">{lead.name}</td>
                            <td className="px-6 py-4 text-sm text-gray-700">{lead.phone}</td>
                            <td className="px-6 py-4 text-sm text-gray-700">{lead.source}</td>
                            <td className="px-6 py-4"><Badge color={statusMap[lead.status] || 'gray'}>{lead.status}</Badge></td>
                        </tr>
                    ))}
                </tbody>
            </table></div></Card>
            
            <Modal show={showModal} onClose={() => setShowModal(false)} title="Input Lead Baru" size="lg">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Alert message={error} type="error" />
                    <div className="grid grid-cols-2 gap-4">
                        <div><Label>Nama</Label><Input name="name" value={form.name} onChange={handleChange} required /></div>
                        <div><Label>No. HP (WA)</Label><Input name="phone" value={form.phone} onChange={handleChange} required /></div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div><Label>Sumber Lead</Label><Select name="source" value={form.source} onChange={handleChange}>
                            <option>IG</option><option>FB</option><option>Walk-in</option><option>Iklan</option><option>Lainnya</option>
                        </Select></div>
                        <div><Label>Status</Label><Select name="status" value={form.status} onChange={handleChange}>
                            <option value="Warm">Warm</option><option value="Hot">Hot</option><option value="Cold">Cold</option>
                        </Select></div>
                    </div>
                    <div><Label>Catatan</Label><Input name="notes" value={form.notes} onChange={handleChange} /></div>
                    <Button type="submit" icon={UserPlus}>Simpan Lead</Button>
                </form>
            </Modal>
        </div>
    );
};

// --- 5. PACKAGES VIEW ---
const PackagesView = () => {
    const { apiFetch } = useApi();
    const [packages, setPackages] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [form, setForm] = useState({ title: '', departure_date: '', price_quad: 0, price_triple: 0, price_double: 0 });
    const [error, setError] = useState('');

    const fetchData = async () => {
        setLoading(true); setError('');
        apiFetch('/packages').then(setPackages).catch(err => setError(err.message)).finally(() => setLoading(false));
    };
    useEffect(() => { fetchData(); }, []);
    
    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault(); setError('');
        try {
            const price_details = {
                Quad: form.price_quad,
                Triple: form.price_triple,
                Double: form.price_double
            };
            await apiFetch('/packages', { method: 'POST', body: JSON.stringify({ ...form, price_details }) });
            setShowModal(false); setForm({ title: '', departure_date: '', price_quad: 0, price_triple: 0, price_double: 0 });
            fetchData();
        } catch (err) { setError(err.message); }
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div><h1 className="text-2xl font-bold text-gray-900">Manajemen Paket</h1><p className="text-gray-500 text-sm">Input paket, tanggal berangkat, dan harga kamar.</p></div>
                <Button onClick={() => setShowModal(true)} icon={Plus}>Paket Baru</Button>
            </div>
            <Alert message={error} type="error" />
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {loading && <div className="p-4 text-center text-gray-500 col-span-3">Memuat paket...</div>}
                {!loading && packages.length === 0 && <div className="p-4 text-center text-gray-500 col-span-3">Belum ada paket.</div>}
                {packages.map(pkg => (
                    <Card key={pkg.id} className="flex flex-col">
                        <div className="p-5">
                            <h3 className="text-lg font-bold text-gray-900">{pkg.title}</h3>
                            <p className="text-sm text-gray-500 mt-1">
                                <CalendarCheck size={14} className="inline mr-1" /> Berangkat: {new Date(pkg.departure_date).toLocaleDateString('id-ID', {day: '2-digit', month: 'long', year: 'numeric'})}
                            </p>
                        </div>
                        <div className="px-5 py-4 bg-gray-50 border-t border-gray-100 mt-auto grid grid-cols-3 divide-x divide-gray-200">
                            <PriceDisplay label="Quad" price={JSON.parse(pkg.price_details || '{}').Quad} />
                            <PriceDisplay label="Triple" price={JSON.parse(pkg.price_details || '{}').Triple} />
                            <PriceDisplay label="Double" price={JSON.parse(pkg.price_details || '{}').Double} />
                        </div>
                    </Card>
                ))}
            </div>
            
            <Modal show={showModal} onClose={() => setShowModal(false)} title="Input Paket Baru" size="lg">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Alert message={error} type="error" />
                    <div><Label>Nama Paket</Label><Input name="title" value={form.title} onChange={handleChange} required /></div>
                    <div><Label>Tanggal Berangkat</Label><Input name="departure_date" type="date" value={form.departure_date} onChange={handleChange} required /></div>
                    <div className="grid grid-cols-3 gap-4">
                        <div><Label>Harga Quad (Rp)</Label><Input name="price_quad" type="number" value={form.price_quad} onChange={handleChange} /></div>
                        <div><Label>Harga Triple (Rp)</Label><Input name="price_triple" type="number" value={form.price_triple} onChange={handleChange} /></div>
                        <div><Label>Harga Double (Rp)</Label><Input name="price_double" type="number" value={form.price_double} onChange={handleChange} /></div>
                    </div>
                    <Button type="submit" icon={Plus}>Simpan Paket</Button>
                </form>
            </Modal>
        </div>
    );
};
const PriceDisplay = ({ label, price }) => {
    if (!price || price == 0) return <div className="text-center"><div className="text-xs text-gray-400">{label}</div><div className="text-sm font-semibold text-gray-400">-</div></div>;
    return <div className="text-center"><div className="text-xs text-gray-500">{label}</div><div className="text-sm font-semibold text-gray-800">Rp {Number(price).toLocaleString('id-ID', {maximumFractionDigits: 0})}</div></div>
};

// --- 6. HR VIEW ---
const HRView = () => {
    const { apiFetch } = useApi();
    const [attendance, setAttendance] = useState([]);
    const [leaveRequests, setLeaveRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [form, setForm] = useState({ type: 'Izin', reason: '', date: new Date().toISOString().split('T')[0] });
    const [error, setError] = useState('');

    const fetchData = async () => {
        setLoading(true); setError('');
        try {
            const [attData, leaveData] = await Promise.all([
                apiFetch('/hr/attendance'),
                apiFetch('/hr/leave')
            ]);
            setAttendance(attData);
            setLeaveRequests(leaveData);
        } catch (err) { setError(err.message); }
        finally { setLoading(false); }
    };
    useEffect(() => { fetchData(); }, []);
    
    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleCheckIn = async () => {
        setError('');
        try {
            await apiFetch('/hr/checkin', { method: 'POST' });
            fetchData();
        } catch (err) { setError(err.message); }
    };
    
    const handleSubmitIzin = async (e) => {
        e.preventDefault(); setError('');
        try {
            await apiFetch('/hr/leave', { method: 'POST', body: JSON.stringify(form) });
            setShowModal(false); setForm({ type: 'Izin', reason: '', date: new Date().toISOString().split('T')[0] });
            fetchData();
        } catch (err) { setError(err.message); }
    };
    
    const handleApproveLeave = async (id) => {
        setError('');
        try {
            await apiFetch(`/hr/leave/${id}`, { method: 'PUT', body: JSON.stringify({ status: 'Approved' }) });
            fetchData();
        } catch (err) { setError(err.message); }
    };

    const today = new Date().toISOString().split('T')[0];
    const hasCheckedIn = attendance.some(att => att.date === today && att.status === 'Hadir' && att.user_name === userName);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div><h1 className="text-2xl font-bold text-gray-900">HRD (Absen & Cuti)</h1><p className="text-gray-500 text-sm">Kelola kehadiran dan pengajuan cuti karyawan.</p></div>
                <div className="flex space-x-2">
                    <Button onClick={() => setShowModal(true)} variant="secondary" icon={CalendarOff}>Ajukan Izin/Sakit</Button>
                    <Button onClick={handleCheckIn} icon={CheckCircle2} disabled={hasCheckedIn}>
                        {hasCheckedIn ? 'Sudah Check-in' : 'Check-in Hari Ini'}
                    </Button>
                </div>
            </div>
            <Alert message={error} type="error" />
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <h3 className="text-lg font-bold text-gray-900 p-5 border-b border-gray-100">Riwayat Absensi (5 Terakhir)</h3>
                    <div className="divide-y divide-gray-100">
                        {loading && <div className="p-4 text-center text-gray-500">Memuat...</div>}
                        {attendance.slice(0, 5).map(att => (
                            <div key={att.id} className="flex items-center p-4">
                                <div className="flex-1"><span className="font-medium text-gray-800">{att.user_name}</span></div>
                                <div className="text-gray-600 text-sm">{new Date(att.date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short'})}</div>
                                <Badge color={att.status === 'Hadir' ? 'green' : 'yellow'} className="ml-4">{att.status}</Badge>
                            </div>
                        ))}
                    </div>
                </Card>
                <Card>
                    <h3 className="text-lg font-bold text-gray-900 p-5 border-b border-gray-100">Pengajuan Cuti (Pending)</h3>
                    <div className="divide-y divide-gray-100">
                        {loading && <div className="p-4 text-center text-gray-500">Memuat...</div>}
                        {leaveRequests.filter(req => req.status === 'Pending').map(req => (
                            <div key={req.id} className="flex items-center p-4">
                                <div className="flex-1">
                                    <span className="font-medium text-gray-800">{req.user_name}</span>
                                    <span className="text-sm text-gray-500"> ({req.type})</span>
                                    <p className="text-xs text-gray-500">{req.reason}</p>
                                </div>
                                <div className="text-gray-600 text-sm">{new Date(req.date).toLocaleDateString('id-ID', {day:'2-digit', month:'short'})}</div>
                                {isAdmin && <Button onClick={() => handleApproveLeave(req.id)} variant="ghost" className="ml-2">Approve</Button>}
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
            
            <Modal show={showModal} onClose={() => setShowModal(false)} title="Form Izin/Sakit/Cuti" size="md">
                <form onSubmit={handleSubmitIzin} className="space-y-4">
                    <Alert message={error} type="error" />
                    <div><Label>Tipe</Label><Select name="type" value={form.type} onChange={handleChange}>
                        <option>Izin</option><option>Sakit</option><option>Cuti</option>
                    </Select></div>
                    <div><Label>Tanggal</Label><Input name="date" type="date" value={form.date} onChange={handleChange} required /></div>
                    <div><Label>Alasan</Label><Input name="reason" value={form.reason} onChange={handleChange} required /></div>
                    <Button type="submit" icon={Check}>Ajukan</Button>
                </form>
            </Modal>
        </div>
    );
};

// --- 7. USERS VIEW ---
const UsersView = () => {
    const { apiFetch } = useApi();
    const [users, setUsers] = useState([]);
    useEffect(() => { apiFetch('/users').then(setUsers).catch(err => console.error(err)); }, []);
    
    return (
        <div className="space-y-6">
            <div><h1 className="text-2xl font-bold text-gray-900">Manajemen Karyawan</h1><p className="text-gray-500 text-sm">Daftar semua pengguna yang terdaftar di sistem.</p></div>
            <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full">
                <thead className="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {users.map(user => (
                        <tr key={user.id} className="hover:bg-gray-50">
                            <td className="px-6 py-4 text-sm font-medium text-gray-900">{user.name}</td>
                            <td className="px-6 py-4 text-sm text-gray-700">{user.email}</td>
                            <td className="px-6 py-4"><Badge color={user.role === 'owner' || user.role === 'administrator' ? 'red' : 'blue'}>{user.role}</Badge></td>
                        </tr>
                    ))}
                </tbody>
            </table></div></Card>
        </div>
    );
};

// --- 8. FINANCE VIEW (Admin Only) ---
const FinanceView = () => {
    const { apiFetch } = useApi();
    const [logs, setLogs] = useState([]);
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [form, setForm] = useState({ type: 'Kasbon', amount: 0, description: '', user_id: '' });
    const [error, setError] = useState('');

    const fetchData = async () => {
        setLoading(true); setError('');
        try {
            const [logData, userData] = await Promise.all([
                apiFetch('/finance'),
                apiFetch('/users')
            ]);
            setLogs(logData);
            setUsers(userData.filter(u => u.role === 'karyawan')); // Hanya karyawan
        } catch (err) { setError(err.message); }
        finally { setLoading(false); }
    };
    useEffect(() => { fetchData(); }, []);
    
    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault(); setError('');
        try {
            await apiFetch('/finance', { method: 'POST', body: JSON.stringify(form) });
            setShowModal(false); setForm({ type: 'Kasbon', amount: 0, description: '', user_id: '' });
            fetchData();
        } catch (err) { setError(err.message); }
    };
    
    const totalPemasukan = logs.filter(l => l.type === 'Pemasukan').reduce((sum, l) => sum + Number(l.amount), 0);
    const totalPengeluaran = logs.filter(l => l.type !== 'Pemasukan').reduce((sum, l) => sum + Number(l.amount), 0);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div><h1 className="text-2xl font-bold text-gray-900">Keuangan Kantor (Buku Besar)</h1><p className="text-gray-500 text-sm">Catatan Pemasukan (dari Jemaah) dan Pengeluaran (Gaji, Kasbon).</p></div>
                <Button onClick={() => setShowModal(true)} icon={Plus}>Catat Manual</Button>
            </div>
            
            <Alert message={error} type="error" />
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card className="p-5"><div className="text-sm text-gray-500">Total Pemasukan</div><div className="text-2xl font-bold text-green-600">Rp {totalPemasukan.toLocaleString('id-ID')}</div></Card>
                <Card className="p-5"><div className="text-sm text-gray-500">Total Pengeluaran</div><div className="text-2xl font-bold text-red-600">Rp {totalPengeluaran.toLocaleString('id-ID')}</div></Card>
                <Card className="p-5"><div className="text-sm text-gray-500">Saldo Kas</div><div className="text-2xl font-bold text-blue-600">Rp {(totalPemasukan - totalPengeluaran).toLocaleString('id-ID')}</div></Card>
            </div>
            
            <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full">
                <thead className="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Deskripsi</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Tipe</th>
                        <th className="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Nominal (Rp)</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {loading && <tr><td colSpan="4" className="p-4 text-center text-gray-500">Memuat...</td></tr>}
                    {!loading && logs.length === 0 && <tr><td colSpan="4" className="p-4 text-center text-gray-500">Belum ada catatan keuangan.</td></tr>}
                    {logs.map(log => (
                        <tr key={log.id} className="hover:bg-gray-50">
                            <td className="px-6 py-4 text-sm text-gray-700">{new Date(log.date).toLocaleDateString('id-ID', {day: '2-digit', month: 'short'})}</td>
                            <td className="px-6 py-4 text-sm font-medium text-gray-900">{log.description}</td>
                            <td className="px-6 py-4"><Badge color={log.type === 'Pemasukan' ? 'green' : (log.type === 'Kasbon' ? 'yellow' : 'red')}>{log.type}</Badge></td>
                            <td className={`px-6 py-4 text-sm font-bold text-right ${log.type === 'Pemasukan' ? 'text-green-600' : 'text-red-600'}`}>
                                {log.type !== 'Pemasukan' && '-'}{Number(log.amount).toLocaleString('id-ID')}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table></div></Card>
            
            <Modal show={showModal} onClose={() => setShowModal(false)} title="Catat Keuangan Manual" size="lg">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <Alert message={error} type="error" />
                    <div className="grid grid-cols-2 gap-4">
                        <div><Label>Tipe</Label><Select name="type" value={form.type} onChange={handleChange}>
                            <option>Kasbon</option><option>Gaji</option><option>Operasional</option><option>Lainnya</option>
                        </Select></div>
                        <div><Label>Nominal (Rp)</Label><Input name="amount" type="number" value={form.amount} onChange={handleChange} required /></div>
                    </div>
                    <div><Label>Karyawan (Jika Kasbon/Gaji)</Label><Select name="user_id" value={form.user_id} onChange={handleChange}>
                        <option value="">-- Tidak Terkait Karyawan --</option>
                        {users.map(user => (<option key={user.id} value={user.id}>{user.name}</option>))}
                    </Select></div>
                    <div><Label>Deskripsi</Label><Input name="description" value={form.description} onChange={handleChange} required /></div>
                    <Button type="submit" icon={Check}>Simpan Catatan</Button>
                </form>
            </Modal>
        </div>
    );
};

// --- 9. LOGS VIEW (Admin Only) ---
const LogsView = () => {
    const { apiFetch } = useApi();
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    useEffect(() => { 
        setLoading(true);
        apiFetch('/logs').then(setLogs).catch(err => console.error(err)).finally(() => setLoading(false)); 
    }, []);
    
    return (
        <div className="space-y-6">
            <div><h1 className="text-2xl font-bold text-gray-900">Log Aktivitas (CCTV)</h1><p className="text-gray-500 text-sm">Melihat siapa melakukan apa dan kapan.</p></div>
            <Card className="overflow-hidden"><div className="overflow-x-auto"><table className="w-full">
                <thead className="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Waktu</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Pengguna</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Aksi</th>
                        <th className="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Detail</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {loading && <tr><td colSpan="4" className="p-4 text-center text-gray-500">Memuat log...</td></tr>}
                    {!loading && logs.length === 0 && <tr><td colSpan="4" className="p-4 text-center text-gray-500">Belum ada aktivitas.</td></tr>}
                    {logs.map(log => (
                        <tr key={log.id} className="hover:bg-gray-50">
                            <td className="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">{new Date(log.timestamp).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}</td>
                            <td className="px-6 py-4 text-sm font-medium text-gray-900">{log.user_name}</td>
                            <td className="px-6 py-4"><Badge color="blue">{log.action}</Badge></td>
                            <td className="px-6 py-4 text-sm text-gray-700">{log.details}</td>
                        </tr>
                    ))}
                </tbody>
            </table></div></Card>
        </div>
    );
};

// --- RENDER APLIKASI ---
const rootEl = document.getElementById('root');
if (rootEl) {
    const root = ReactDOM.createRoot(rootEl);
    root.render(
      <React.StrictMode>
        <DashboardLayout />
      </React.StrictMode>
    );
} else {
    console.error("Target element #root not found. React app failed to load.");
}