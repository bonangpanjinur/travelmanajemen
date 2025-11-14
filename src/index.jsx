import React, { useState, useEffect, createContext, useContext, useCallback, useMemo } from 'react';
import { createRoot } from 'react-dom/client';

// --- API Client ---
const createApiClient = (getToken) => {
    const request = async (endpoint, options = {}) => {
        const token = getToken();
        // Ambil data wpData di dalam fungsi, bukan di luar
        const wpData = window.umh_wp_data || { api_url: '/wp-json/umh/v1/', api_nonce: '' };
        
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers,
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        // Jika super admin, gunakan Nonce
        if (wpData.is_wp_admin && wpData.api_nonce) {
             headers['X-WP-Nonce'] = wpData.api_nonce;
        }

        const response = await fetch(`${wpData.api_url}${endpoint}`, {
            ...options,
            headers,
        });

        if (response.status === 204) { // No Content
            return null;
        }
        
        const responseData = await response.json();

        if (!response.ok) {
            throw new Error(responseData.message || `Error ${response.status}`);
        }
        
        return responseData;
    };

    return {
        get: (endpoint, options = {}) => request(endpoint, { ...options, method: 'GET' }),
        post: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'POST', body: JSON.stringify(body) }),
        put: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'PUT', body: JSON.stringify(body) }),
        del: (endpoint, options = {}) => request(endpoint, { ...options, method: 'DELETE' }),
    };
};

// --- Auth Context ---
const AuthContext = createContext(null);

const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(() => localStorage.getItem('umh_auth_token'));
    const [isLoading, setIsLoading] = useState(true);
    
    const apiClient = useMemo(() => createApiClient(() => token), [token]);

    useEffect(() => {
        const bootstrapAuth = async () => {
            const wpData = window.umh_wp_data;
            const existingToken = localStorage.getItem('umh_auth_token');
            try {
                if (wpData && wpData.is_wp_admin) {
                    console.log('Admin WP terdeteksi, mencoba auto-login...');
                    try {
                        const response = await fetch(`${wpData.api_url}auth/wp-login`, {
                            method: 'POST',
                            headers: {
                                'X-WP-Nonce': wpData.api_nonce, 
                                'Content-Type': 'application/json',
                            },
                        });
                        
                        if (!response.ok) {
                            const errorData = await response.json();
                            throw new Error(errorData.message || 'WP Admin auto-login gagal.');
                        }
                        
                        const data = await response.json(); 
                        console.log('WP Admin auto-login berhasil.', data);
                        setToken(data.token);
                        setUser(data.user);
                        localStorage.setItem('umh_auth_token', data.token);
                        
                    } catch (adminLoginError) {
                        console.error('WP Admin auto-login error:', adminLoginError);
                        localStorage.removeItem('umh_auth_token');
                        setToken(null);
                        setUser(null);
                    }
                
                } else if (existingToken) {
                    console.log('Token lokal ditemukan, memverifikasi...');
                    try {
                        const apiClientForVerify = createApiClient(() => existingToken);
                        const userData = await apiClientForVerify.get('users/me');
                        
                        console.log('Verifikasi token berhasil.');
                        setUser(userData);
                        setToken(existingToken);
                    
                    } catch (verifyError) {
                        console.warn('Verifikasi token gagal:', verifyError);
                        localStorage.removeItem('umh_auth_token');
                        setToken(null);
                        setUser(null);
                    }

                } else {
                    console.log('Bukan admin & tidak ada token. Tampilkan login.');
                }

            } catch (error) {
                console.error('Bootstrap auth error:', error);
                localStorage.removeItem('umh_auth_token');
                setToken(null);
                setUser(null);
            } finally {
                setIsLoading(false);
            }
        };

        bootstrapAuth();
    }, []); 

    const handleLoginSuccess = (data) => {
        console.log('Login kustom berhasil.');
        setToken(data.token);
        setUser(data.user);
        localStorage.setItem('umh_auth_token', data.token);
    };

    const logout = () => {
        console.log('Logout.');
        setToken(null);
        setUser(null);
        localStorage.removeItem('umh_auth_token');
    };

    const authContextValue = {
        user,
        token,
        isLoading,
        api: apiClient,
        logout,
        login: handleLoginSuccess,
    };

    return (
        <AuthContext.Provider value={authContextValue}>
            {children}
        </AuthContext.Provider>
    );
};

const useAuth = () => {
    const context = useContext(AuthContext);
    if (context === null) {
        throw new Error('useAuth harus digunakan di dalam AuthProvider');
    }
    return context;
};

// --- Login Form ---
const CustomLoginForm = ({ onLoginSuccess }) => {
    const [email, setEmail] = useState(''); 
    const [password, setPassword] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const wpData = window.umh_wp_data || { api_url: '/wp-json/umh/v1/' };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(`${wpData.api_url}users/login`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email, password: password }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Login gagal.');
            }
            onLoginSuccess(data);

        } catch (err) {
            setError(err.message);
            setIsLoading(false);
        }
    };

    return (
        <div style={styles.loginContainer}>
            <div style={styles.loginBox}>
                <h2 style={styles.loginTitle}>Login Aplikasi</h2>
                <p style={styles.loginSubtitle}>Silakan masuk (Owner/Karyawan)</p>
                <form onSubmit={handleSubmit}>
                    <div style={styles.inputGroup}>
                        <label htmlFor="email" style={styles.label}>Email</label>
                        <input
                            type="email"
                            id="email"
                            style={styles.input}
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />
                    </div>
                    <div style={styles.inputGroup}>
                        <label htmlFor="password" style={styles.label}>Password</label>
                        <input
                            type="password"
                            id="password"
                            style={styles.input}
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </div>
                    {error && <p style={styles.error}>{error}</p>}
                    <button type="submit" style={styles.button} disabled={isLoading}>
                        {isLoading ? 'Loading...' : 'Login'}
                    </button>
                </form>
            </div>
        </div>
    );
};


// --- [PERBAIKAN 1: Modal] Komponen UI Helper ---

const Modal = ({ show, onClose, title, children }) => {
    if (!show) return null;

    return (
        // 1. Kontainer Utama (z-50)
        <div 
            className="fixed inset-0 z-50 flex justify-center items-center p-4" 
            aria-labelledby="modal-title" 
            role="dialog" 
            aria-modal="true"
        >
            {/* 2. Backdrop/Overlay. Dibuat terpisah. */}
            <div 
                className="fixed inset-0 bg-black bg-opacity-50" 
                aria-hidden="true"
                onClick={onClose} // Menambahkan fungsi tutup saat overlay diklik
            ></div>

            {/* 3. Panel Modal. Diberi 'relative' dan 'z-10' agar di atas backdrop */}
            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col relative z-10">
                {/* Header */}
                <div className="flex justify-between items-center p-5 border-b">
                    <h3 id="modal-title" className="text-xl font-semibold text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                {/* Body (tempat form) */}
                <div className="p-6 overflow-y-auto">
                    {children}
                </div>
            </div>
        </div>
    );
};

// --- [PERBAIKAN 2: Form Input] ---

const FormInput = ({ label, value, onChange, type = 'text', required = false, ...props }) => (
    <div>
        <label className="block text-sm font-medium text-gray-700">{label}</label>
        <input
            type={type}
            value={value}
            onChange={onChange} // DIPERBAIKI: Langsung pass 'onChange'
            required={required}
            {...props}
            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
        />
    </div>
);

const FormTextarea = ({ label, value, onChange, ...props }) => (
    <div>
        <label className="block text-sm font-medium text-gray-700">{label}</label>
        <textarea
            value={value}
            onChange={onChange} // DIPERBAIKI: Langsung pass 'onChange'
            {...props}
            rows="3"
            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
        />
    </div>
);

const FormSelect = ({ label, value, onChange, children, ...props }) => (
     <div>
        <label className="block text-sm font-medium text-gray-700">{label}</label>
        <select
            value={value}
            onChange={onChange} // DIPERBAIKI: Langsung pass 'onChange'
            {...props}
            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
        >
            {children}
        </select>
    </div>
);


const StatCard = ({ title, value }) => (
    <div className="bg-white p-6 rounded-lg shadow">
        <h3 className="text-sm font-medium text-gray-500">{title}</h3>
        <p className="mt-1 text-3xl font-semibold text-gray-900">{value}</p>
    </div>
);


// --- Halaman Dashboard ---
const DashboardComponent = () => {
    const { api } = useAuth();
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                setLoading(true);
                const data = await api.get('stats/totals');
                setStats(data);
                setError(null);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };
        fetchStats();
    }, [api]);

    if (loading) return <div>Memuat statistik...</div>;
    if (error) return <div className="text-red-500">Error: {error}</div>;

    return (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <StatCard title="Total Jemaah" value={stats?.total_jamaah || 0} />
            <StatCard title="Total Paket" value={stats?.total_packages || 0} />
            <StatCard title="Total Pemasukan" value={`Rp ${Number(stats?.total_revenue || 0).toLocaleString('id-ID')}`} />
            <StatCard title="Total Pengeluaran" value={`Rp ${Number(stats?.total_expense || 0).toLocaleString('id-ID')}`} />
        </div>
    );
};


// --- Halaman Manajemen Paket ---
const PackagesComponent = () => {
    const { api } = useAuth();
    const [packages, setPackages] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [currentItem, setCurrentItem] = useState(null);

    // Form state
    const [formState, setFormState] = useState({
        package_name: '',
        price: '',
        departure_date: '',
        duration: '',
        status: 'draft',
    });

    const fetchPackages = useCallback(async () => {
        try {
            setLoading(true);
            const data = await api.get('packages');
            setPackages(data);
            setError(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        fetchPackages();
    }, [fetchPackages]);

    const handleOpenModal = (item = null) => {
        if (item) {
            setIsEditing(true);
            setCurrentItem(item);
            setFormState({
                package_name: item.package_name,
                price: item.price,
                departure_date: item.departure_date.split('T')[0], // Format YYYY-MM-DD
                duration: item.duration,
                status: item.status,
            });
        } else {
            setIsEditing(false);
            setCurrentItem(null);
            setFormState({ package_name: '', price: '', departure_date: '', duration: '', status: 'draft' });
        }
        setShowModal(true);
    };

    const handleCloseModal = () => {
        setShowModal(false);
        setCurrentItem(null);
        setIsEditing(false);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormState(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (isEditing) {
                await api.put(`packages/${currentItem.id}`, formState);
            } else {
                await api.post('packages', formState);
            }
            fetchPackages();
            handleCloseModal();
        } catch (err) {
            setError(`Gagal menyimpan: ${err.message}`);
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Apakah Anda yakin ingin menghapus paket ini?')) return;
        try {
            await api.del(`packages/${id}`);
            fetchPackages();
        } catch (err) {
            setError(`Gagal menghapus: ${err.message}`);
        }
    };

    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-2xl font-bold">Manajemen Paket Umroh</h2>
                <button onClick={() => handleOpenModal()} className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                    + Tambah Paket
                </button>
            </div>
            {error && <div className="bg-red-100 text-red-700 p-3 rounded-md mb-4">{error}</div>}
            
            {loading ? (
                <div>Memuat data paket...</div>
            ) : (
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                         <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Paket</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keberangkatan</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {packages.length === 0 && <tr><td colSpan="5" className="px-6 py-4 text-center text-gray-500">Tidak ada data.</td></tr>}
                            {packages.map(pkg => (
                                <tr key={pkg.id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{pkg.package_name}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Rp {Number(pkg.price).toLocaleString('id-ID')}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(pkg.departure_date).toLocaleDateString('id-ID')}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{pkg.status}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <button onClick={() => handleOpenModal(pkg)} className="text-indigo-600 hover:text-indigo-900">Edit</button>
                                        <button onClick={() => handleDelete(pkg.id)} className="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Modal show={showModal} onClose={handleCloseModal} title={isEditing ? 'Edit Paket' : 'Tambah Paket Baru'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormInput label="Nama Paket" name="package_name" value={formState.package_name} onChange={handleChange} required />
                    <FormInput label="Harga" name="price" type="number" value={formState.price} onChange={handleChange} required />
                    <FormInput label="Tanggal Keberangkatan" name="departure_date" type="date" value={formState.departure_date} onChange={handleChange} required />
                    <FormInput label="Durasi (hari)" name="duration" type="number" value={formState.duration} onChange={handleChange} required />
                    <FormSelect label="Status" name="status" value={formState.status} onChange={handleChange}>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Archived</option>
                    </FormSelect>
                    <div className="text-right pt-4">
                        <button type="button" onClick={handleCloseModal} className="mr-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
};


// --- Halaman Manajemen Jemaah ---
const JamaahComponent = () => {
    const { api } = useAuth();
    const [jamaahList, setJamaahList] = useState([]);
    const [packages, setPackages] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [currentItem, setCurrentItem] = useState(null);

    const initialFormState = {
        full_name: '',
        id_number: '',
        phone: '',
        email: '',
        package_id: '',
        status: 'pending',
        payment_status: 'pending',
    };
    const [formState, setFormState] = useState(initialFormState);

    const fetchDependencies = useCallback(async () => {
        try {
            setLoading(true);
            const [jamaahData, packagesData] = await Promise.all([
                api.get('jamaah'),
                api.get('packages')
            ]);
            setJamaahList(jamaahData);
            setPackages(packagesData);
            setError(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        fetchDependencies();
    }, [fetchDependencies]);
    
    const handleOpenModal = (item = null) => {
        if (item) {
            setIsEditing(true);
            setCurrentItem(item);
            setFormState({
                full_name: item.full_name,
                id_number: item.id_number,
                phone: item.phone || '',
                email: item.email || '',
                package_id: item.package_id,
                status: item.status,
                payment_status: item.payment_status,
            });
        } else {
            setIsEditing(false);
            setCurrentItem(null);
            setFormState(initialFormState);
        }
        setShowModal(true);
    };
    
    const handleCloseModal = () => {
        setShowModal(false);
        setCurrentItem(null);
        setIsEditing(false);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormState(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (isEditing) {
                await api.put(`jamaah/${currentItem.id}`, formState);
            } else {
                await api.post('jamaah', formState);
            }
            fetchDependencies(); // Muat ulang semua data
            handleCloseModal();
        } catch (err) {
            setError(`Gagal menyimpan: ${err.message}`);
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Apakah Anda yakin ingin menghapus jemaah ini?')) return;
        try {
            await api.del(`jamaah/${id}`);
            fetchDependencies(); // Muat ulang semua data
        } catch (err) {
            setError(`Gagal menghapus: ${err.message}`);
        }
    };

    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-2xl font-bold">Manajemen Jemaah</h2>
                <button onClick={() => handleOpenModal()} className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                    + Tambah Jemaah
                </button>
            </div>
            {error && <div className="bg-red-100 text-red-700 p-3 rounded-md mb-4">{error}</div>}

            {loading ? (
                <div>Memuat data jemaah dan paket...</div>
            ) : (
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paket</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pembayaran</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {jamaahList.length === 0 && <tr><td colSpan="5" className="px-6 py-4 text-center text-gray-500">Tidak ada data.</td></tr>}
                            {jamaahList.map(jemaah => (
                                <tr key={jemaah.id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{jemaah.full_name}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{jemaah.package_name || 'N/A'}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{jemaah.status}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{jemaah.payment_status}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <button onClick={() => handleOpenModal(jemaah)} className="text-indigo-600 hover:text-indigo-900">Edit</button>
                                        <button onClick={() => handleDelete(jemaah.id)} className="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Modal show={showModal} onClose={handleCloseModal} title={isEditing ? 'Edit Jemaah' : 'Tambah Jemaah Baru'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormInput label="Nama Lengkap" name="full_name" value={formState.full_name} onChange={handleChange} required />
                    <FormInput label="No. KTP" name="id_number" value={formState.id_number} onChange={handleChange} required />
                    <FormInput label="No. Telepon" name="phone" value={formState.phone} onChange={handleChange} />
                    <FormInput label="Email" name="email" type="email" value={formState.email} onChange={handleChange} />
                    <FormSelect label="Paket" name="package_id" value={formState.package_id} onChange={handleChange} required>
                        <option value="">Pilih Paket</option>
                        {packages.map(pkg => <option key={pkg.id} value={pkg.id}>{pkg.package_name}</option>)}
                    </FormSelect>
                    <FormSelect label="Status Pendaftaran" name="status" value={formState.status} onChange={handleChange}>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="waitlist">Waitlist</option>
                    </FormSelect>
                    <FormSelect label="Status Pembayaran" name="payment_status" value={formState.payment_status} onChange={handleChange}>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="refunded">Refunded</option>
                    </FormSelect>
                    
                    <div className="text-right pt-4">
                        <button type="button" onClick={handleCloseModal} className="mr-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
};


// --- Halaman Manajemen Keuangan ---
const FinanceComponent = () => {
    const { api } = useAuth();
    const [transactions, setTransactions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showModal, setShowModal] = useState(false);
    
    const initialFormState = {
        transaction_type: 'expense',
        description: '',
        amount: '',
        transaction_date: new Date().toISOString().split('T')[0],
    };
    const [formState, setFormState] = useState(initialFormState);

     const fetchTransactions = useCallback(async () => {
        try {
            setLoading(true);
            const data = await api.get('finance');
            setTransactions(data);
            setError(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        fetchTransactions();
    }, [fetchTransactions]);
    
    const handleOpenModal = () => {
        setFormState(initialFormState);
        setShowModal(true);
    };
    
    const handleCloseModal = () => setShowModal(false);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormState(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            await api.post('finance', formState);
            fetchTransactions();
            handleCloseModal();
        } catch (err) {
            setError(`Gagal menyimpan: ${err.message}`);
        }
    };
    
     const handleDelete = async (id) => {
        if (!window.confirm('Apakah Anda yakin ingin menghapus transaksi ini?')) return;
        try {
            await api.del(`finance/${id}`);
            fetchTransactions();
        } catch (err) {
            setError(`Gagal menghapus: ${err.message}`);
        }
    };

    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-2xl font-bold">Manajemen Keuangan</h2>
                <button onClick={handleOpenModal} className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                    + Catat Transaksi
                </button>
            </div>
            {error && <div className="bg-red-100 text-red-700 p-3 rounded-md mb-4">{error}</div>}
            
             {loading ? (
                <div>Memuat data keuangan...</div>
            ) : (
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                         <tbody className="bg-white divide-y divide-gray-200">
                            {transactions.length === 0 && <tr><td colSpan="5" className="px-6 py-4 text-center text-gray-500">Tidak ada data.</td></tr>}
                            {transactions.map(trx => (
                                <tr key={trx.id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{new Date(trx.transaction_date).toLocaleDateString('id-ID')}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{trx.description}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                            trx.transaction_type === 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                        }`}>
                                            {trx.transaction_type}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp {Number(trx.amount).toLocaleString('id-ID')}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onClick={() => handleDelete(trx.id)} className="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            
             <Modal show={showModal} onClose={handleCloseModal} title="Catat Transaksi Baru">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormSelect label="Tipe Transaksi" name="transaction_type" value={formState.transaction_type} onChange={handleChange}>
                        <option value="expense">Pengeluaran (Expense)</option>
                        <option value="income">Pemasukan (Income)</option>
                    </FormSelect>
                    <FormInput label="Tanggal" name="transaction_date" type="date" value={formState.transaction_date} onChange={handleChange} required />
                    <FormInput label="Jumlah (Rp)" name="amount" type="number" value={formState.amount} onChange={handleChange} required />
                    <FormTextarea label="Deskripsi" name="description" value={formState.description} onChange={handleChange} />
                    
                    <div className="text-right pt-4">
                        <button type="button" onClick={handleCloseModal} className="mr-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
};


// --- Halaman Manajemen Tugas ---
const TasksComponent = () => {
    const { api } = useAuth();
    const [tasks, setTasks] = useState([]);
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showModal, setShowModal] = useState(false);
    
    const initialFormState = { title: '', description: '', assigned_to_user_id: '', due_date: '', priority: 'medium', status: 'pending' };
    const [formState, setFormState] = useState(initialFormState);

    const fetchDependencies = useCallback(async () => {
        try {
            setLoading(true);
            const [tasksData, usersData] = await Promise.all([
                api.get('tasks'),
                api.get('users') // Ambil user untuk dropdown 'assign'
            ]);
            setTasks(tasksData);
            setUsers(usersData);
            setError(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        fetchDependencies();
    }, [fetchDependencies]);

    const handleOpenModal = () => {
        setFormState(initialFormState);
        setShowModal(true);
    };

    const handleCloseModal = () => setShowModal(false);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormState(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            // API Controller kita otomatis mengisi 'created_by_user_id'
            await api.post('tasks', formState);
            fetchDependencies();
            handleCloseModal();
        } catch (err) {
            setError(`Gagal menyimpan: ${err.message}`);
        }
    };
    
    const handleDelete = async (id) => {
        if (!window.confirm('Apakah Anda yakin ingin menghapus tugas ini?')) return;
        try {
            await api.del(`tasks/${id}`);
            fetchDependencies();
        } catch (err) {
            setError(`Gagal menghapus: ${err.message}`);
        }
    };
    
    const getUserName = (id) => users.find(u => u.id === id)?.full_name || 'N/A';

    return (
         <div>
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-2xl font-bold">Manajemen Tugas</h2>
                <button onClick={handleOpenModal} className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                    + Tambah Tugas
                </button>
            </div>
            {error && <div className="bg-red-100 text-red-700 p-3 rounded-md mb-4">{error}</div>}
            
             {loading ? (
                <div>Memuat data tugas...</div>
            ) : (
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tugas</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ditugaskan Kepada</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prioritas</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                         <tbody className="bg-white divide-y divide-gray-200">
                            {tasks.length === 0 && <tr><td colSpan="5" className="px-6 py-4 text-center text-gray-500">Tidak ada data.</td></tr>}
                            {tasks.map(task => (
                                <tr key={task.id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{task.title}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{getUserName(task.assigned_to_user_id)}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{task.status}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{task.priority}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        {/* TODO: Add Edit */}
                                        <button onClick={() => handleDelete(task.id)} className="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            
             <Modal show={showModal} onClose={handleCloseModal} title="Tugas Baru">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormInput label="Judul Tugas" name="title" value={formState.title} onChange={handleChange} required />
                    <FormSelect label="Tugaskan Kepada" name="assigned_to_user_id" value={formState.assigned_to_user_id} onChange={handleChange}>
                        <option value="">Pilih Karyawan</option>
                        {users.map(user => <option key={user.id} value={user.id}>{user.full_name}</option>)}
                    </FormSelect>
                    <FormInput label="Batas Waktu" name="due_date" type="date" value={formState.due_date} onChange={handleChange} />
                    <FormSelect label="Prioritas" name="priority" value={formState.priority} onChange={handleChange}>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </FormSelect>
                    <FormTextarea label="Deskripsi" name="description" value={formState.description} onChange={handleChange} />
                    
                    <div className="text-right pt-4">
                        <button type="button" onClick={handleCloseModal} className="mr-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
};


// --- Halaman Manajemen User ---
const UsersComponent = () => {
    const { api } = useAuth();
    const [userList, setUserList] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [currentItem, setCurrentItem] = useState(null);
    
    const initialFormState = { full_name: '', email: '', password: '', role: 'karyawan', phone: '' };
    const [formState, setFormState] = useState(initialFormState);

    const fetchUsers = useCallback(async () => {
        try {
            setLoading(true);
            const data = await api.get('users');
            setUserList(data);
            setError(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        fetchUsers();
    }, [fetchUsers]);
    
    const handleOpenModal = (item = null) => {
        if (item) {
            setIsEditing(true);
            setCurrentItem(item);
            setFormState({
                full_name: item.full_name,
                email: item.email,
                password: '', // Jangan tampilkan password
                role: item.role,
                phone: item.phone || '',
            });
        } else {
            setIsEditing(false);
            setCurrentItem(null);
            setFormState(initialFormState);
        }
        setShowModal(true);
    };
    
    const handleCloseModal = () => {
        setShowModal(false);
        setCurrentItem(null);
        setIsEditing(false);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormState(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            const payload = {...formState};
            // Jangan kirim password kosong saat edit jika tidak diubah
            if (isEditing && !payload.password) {
                delete payload.password;
            }

            if (isEditing) {
                await api.put(`users/${currentItem.id}`, payload);
            } else {
                await api.post('users', payload);
            }
            fetchUsers();
            handleCloseModal();
        } catch (err) {
             setError(`Gagal menambah: ${err.message}`);
        }
    };
    
    const handleDelete = async (id) => {
        if (!window.confirm('Apakah Anda yakin ingin menghapus user ini?')) return;
        try {
            await api.del(`users/${id}`);
            fetchUsers();
        } catch (err) {
            setError(`Gagal menghapus: ${err.message}`);
        }
    };

    return (
         <div>
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-2xl font-bold">Manajemen User (Karyawan/Owner)</h2>
                <button onClick={() => handleOpenModal()} className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                    + Tambah User
                </button>
            </div>
            
            {error && <div className="bg-red-100 text-red-700 p-3 rounded-md mb-4">{error}</div>}

            {loading ? (
                <div>Memuat data user...</div>
            ) : (
                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {userList.length === 0 && <tr><td colSpan="4" className="px-6 py-4 text-center text-gray-500">Tidak ada data.</td></tr>}
                            {userList.map(user => (
                                <tr key={user.id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{user.full_name}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{user.email}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{user.role}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <button onClick={() => handleOpenModal(user)} className="text-indigo-600 hover:text-indigo-900">Edit</button>
                                        <button onClick={() => handleDelete(user.id)} className="text-red-600 hover:text-red-900">Hapus</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            
            <Modal show={showModal} onClose={handleCloseModal} title={isEditing ? 'Edit User' : 'Tambah User Baru'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <FormInput label="Nama Lengkap" name="full_name" value={formState.full_name} onChange={handleChange} required />
                    <FormInput label="Email" name="email" type="email" value={formState.email} onChange={handleChange} required />
                    <FormInput label="No. Telepon" name="phone" value={formState.phone} onChange={handleChange} />
                    <FormInput 
                        label={isEditing ? 'Password (Kosongkan jika tidak berubah)' : 'Password'} 
                        name="password" 
                        type="password" 
                        value={formState.password} 
                        onChange={handleChange} 
                        required={!isEditing} 
                    />
                    <FormSelect label="Role" name="role" value={formState.role} onChange={handleChange} required>
                        <option value="karyawan">Karyawan</option>
                        <option value="owner">Owner</option>
                        <option value="admin_staff">Admin Staff</option>
                        <option value="finance_staff">Finance Staff</option>
                        <option value="hr_staff">HR Staff</option>
                        <option value="sopir">Sopir</option>
                    </FormSelect>
                    
                    <div className="text-right pt-4">
                        <button type="button" onClick={handleCloseModal} className="mr-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200">
                            Batal
                        </button>
                        <button type="submit" className="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700">
                            Simpan
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
};


// --- Navigasi Utama ---
const MainNav = ({ currentPage, setPage }) => {
    const { user, logout } = useAuth();
    
    const NavButton = ({ page, label }) => (
        <button
            onClick={() => setPage(page)}
            className={`px-3 py-2 rounded-md text-sm font-medium ${
                currentPage === page 
                ? 'bg-gray-900 text-white' 
                : 'text-gray-700 hover:bg-gray-700 hover:text-white'
            }`}
        >
            {label}
        </button>
    );

    return (
         <header className="bg-white shadow mb-6">
            <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center h-16">
                    <div className="flex items-center">
                        <span className="font-bold text-xl text-gray-800">Umroh Manager</span>
                        <div className="hidden md:flex flex-wrap items-center ml-10 space-x-4">
                            <NavButton page="dashboard" label="Dashboard" />
                            <NavButton page="paket" label="Paket" />
                            <NavButton page="jemaah" label="Jemaah" />
                            <NavButton page="keuangan" label="Keuangan" />
                            <NavButton page="tugas" label="Tugas" />
                            <NavButton page="users" label="Users" />
                            {/* Tambahkan link lain di sini (HR, Marketing, dll) */}
                        </div>
                    </div>
                    <div className="flex items-center">
                         <span className="text-sm text-gray-600 mr-4">
                            Halo, <strong>{user?.full_name || user?.email}</strong> ({user?.role})
                         </span>
                        <button 
                            onClick={logout} 
                            className="text-sm text-gray-700 hover:text-gray-900"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>
    );
};

// --- Komponen App Utama (Router) ---
const App = () => {
    const { user, isLoading, login } = useAuth();
    const [page, setPage] = useState('dashboard'); // State untuk routing

    if (isLoading) {
        return <div style={styles.loading}>Memuat Autentikasi...</div>;
    }

    if (!user) {
        return <CustomLoginForm onLoginSuccess={login} />;
    }
    
    // "Router" Sederhana
    const renderPage = () => {
        switch (page) {
            case 'dashboard': return <DashboardComponent />;
            case 'paket': return <PackagesComponent />;
            case 'jemaah': return <JamaahComponent />;
            case 'keuangan': return <FinanceComponent />;
            case 'tugas': return <TasksComponent />;
            case 'users': return <UsersComponent />;
            default: return <DashboardComponent />;
        }
    };

    return (
        <div className="bg-gray-100 min-h-screen">
            <MainNav currentPage={page} setPage={setPage} />
            <main className="max-w-full mx-auto p-6">
                {renderPage()}
            </main>
        </div>
    );
};

// Render Aplikasi
const rootElement = document.getElementById('umh-react-app-root');
if (rootElement) {
    // Muat Tailwind CSS
    if (!document.getElementById('tailwind-css')) {
        const tailwindScript = document.createElement('script');
        tailwindScript.id = 'tailwind-css';
        tailwindScript.src = 'https://cdn.tailwindcss.com';
        document.head.appendChild(tailwindScript);
    }
    
    const root = createRoot(rootElement);
    root.render(
        <AuthProvider>
            <App />
        </AuthProvider>
    );
}

// --- Styles untuk Login & Loading ---
const styles = {
    loginContainer: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '80vh',
        backgroundColor: '#f9f9f9',
    },
    loginBox: {
        padding: '40px',
        backgroundColor: '#ffffff',
        borderRadius: '8px',
        boxShadow: '0 4px 12px rgba(0,0,0,0.05)',
        width: '100%',
        maxWidth: '400px',
        textAlign: 'center',
    },
    loginTitle: {
        fontSize: '24px',
        fontWeight: '600',
        margin: '0 0 10px',
    },
    loginSubtitle: {
        fontSize: '14px',
        color: '#666',
        marginBottom: '30px',
    },
    inputGroup: {
        marginBottom: '20px',
        textAlign: 'left',
    },
    label: {
        display: 'block',
        marginBottom: '5px',
        fontSize: '13px',
        fontWeight: '500',
        color: '#333',
    },
    input: {
        width: '100%',
        padding: '10px 12px',
        fontSize: '14px',
        border: '1px solid #ddd',
        borderRadius: '4px',
        boxSizing: 'border-box',
    },
    button: {
        width: '100%',
        padding: '12px',
        fontSize: '15px',
        fontWeight: '600',
        color: '#fff',
        backgroundColor: '#007cba',
        border: 'none',
        borderRadius: '4px',
        cursor: 'pointer',
        transition: 'background-color 0.2s',
    },
    error: {
        color: '#d93030',
        fontSize: '13px',
        marginBottom: '15px',
    },
    loading: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '80vh',
        fontSize: '18px',
        fontWeight: '500',
    }
};