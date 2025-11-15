/**
 * File: src/index.jsx
 *
 * PERBAIKAN MENYELURUH (15/11/2025):
 * 1. Merombak hook `useCRUD` untuk mendukung Paginasi dan Pencarian.
 * 2. `useCRUD` kini mengelola state `page`, `searchTerm`, dan `pagination`.
 * 3. `fetchData` di dalam `useCRUD` kini mengirim parameter `?page=...&search=...` ke API.
 * 4. `fetchData` kini mem-parsing respon objek `{ data: [], ... }` dari UMH_CRUD_Controller.
 * 5. Membuat komponen `Pagination` untuk navigasi halaman.
 * 6. Membuat komponen `SearchInput` untuk pencarian.
 * 7. Mengintegrasikan `Pagination` dan `SearchInput` ke semua komponen CRUD
 * (Jamaah, Finance, Tasks, Users, Categories, Flights, Hotels, Roles, dan Packages).
 * 8. Memperbaiki `useCRUD` agar tetap kompatibel dengan API kustom (seperti `api-packages.php`
 * yang kini juga sudah di-upgrade di backend untuk mengembalikan format yg sama).
 * 9. Memperbaiki `handleEdit` di PackagesComponent (sudah benar, hanya memastikan).
 */

import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import ReactDOM from 'react-dom';
import { Users, Package, DollarSign, UserCheck, Search, ArrowLeft, ArrowRight } from 'lucide-react';

// --- Komponen UI Utility (Tidak Berubah) ---

// Utility untuk memformat tanggal (YYYY-MM-DD)
const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        return date.toISOString().split('T')[0];
    } catch (e) {
        return '';
    }
};

// Utility untuk memformat Rupiah
const formatCurrency = (number) => {
    if (number === null || number === undefined) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(number);
};

// Komponen Input Form
const FormInput = ({ label, type = 'text', name, value, onChange, required = false, ...props }) => (
    <div className="mb-4">
        <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor={name}>
            {label} {required && <span className="text-red-500">*</span>}
        </label>
        <input
            type={type}
            id={name}
            name={name}
            value={value || ''}
            onChange={onChange}
            required={required}
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            {...props}
        />
    </div>
);

// Komponen Textarea Form
const FormTextarea = ({ label, name, value, onChange, ...props }) => (
    <div className="mb-4">
        <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor={name}>
            {label}
        </label>
        <textarea
            id={name}
            name={name}
            value={value || ''}
            onChange={onChange}
            rows="3"
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            {...props}
        ></textarea>
    </div>
);

// Komponen Select Form
const FormSelect = ({ label, name, value, onChange, children, required = false, ...props }) => (
    <div className="mb-4">
        <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor={name}>
            {label} {required && <span className="text-red-500">*</span>}
        </label>
        <select
            id={name}
            name={name}
            value={value || ''}
            onChange={onChange}
            required={required}
            className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white"
            {...props}
        >
            {children}
        </select>
    </div>
);

// Komponen Modal
const Modal = ({ show, onClose, title, children, footer }) => {
    if (!show) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 transition-opacity duration-300">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col transition-transform duration-300 transform scale-95 opacity-0 animate-modal-in">
                {/* Header */}
                <div className="flex justify-between items-center p-5 border-b">
                    <h3 className="text-lg font-semibold text-gray-800">{title}</h3>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                {/* Body */}
                <div className="p-6 overflow-y-auto">
                    {children}
                </div>
                
                {/* Footer */}
                <div className="flex justify-end items-center p-5 border-t bg-gray-50 rounded-b-lg">
                    {footer}
                </div>
            </div>
        </div>
    );
};

// Komponen Tombol
const Button = ({ onClick, children, variant = 'primary', type = 'button', ...props }) => {
    const baseStyle = "px-4 py-2 rounded-md font-medium transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed";
    const variants = {
        primary: "bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500",
        secondary: "bg-gray-200 text-gray-800 hover:bg-gray-300 focus:ring-gray-400",
        danger: "bg-red-600 text-white hover:bg-red-700 focus:ring-red-500",
        outline: "bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-blue-500"
    };

    return (
        <button
            type={type}
            onClick={onClick}
            className={`${baseStyle} ${variants[variant]}`}
            {...props}
        >
            {children}
        </button>
    );
};

// Komponen Loading Spinner
const Spinner = () => (
    <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600"></div>
    </div>
);

// Komponen Notifikasi Error/Success
const Alert = ({ message, type = 'error' }) => {
    if (!message) return null;
    const colors = {
        error: "bg-red-100 border-red-400 text-red-700",
        success: "bg-green-100 border-green-400 text-green-700"
    };
    return (
        <div className={`border px-4 py-3 rounded-md my-4 ${colors[type]}`} role="alert">
            <span className="block sm:inline">{message}</span>
        </div>
    );
};

// --- (BARU) Komponen Paginasi ---
const Pagination = ({ pagination, onPageChange }) => {
    const { total_pages, current_page } = pagination;
    if (total_pages <= 1) return null;

    return (
        <div className="flex justify-between items-center mt-4">
            <Button
                variant="outline"
                onClick={() => onPageChange(current_page - 1)}
                disabled={current_page <= 1}
            >
                <ArrowLeft className="w-4 h-4 mr-2" />
                Sebelumnya
            </Button>
            <span className="text-sm text-gray-600">
                Halaman {current_page} dari {total_pages}
            </span>
            <Button
                variant="outline"
                onClick={() => onPageChange(current_page + 1)}
                disabled={current_page >= total_pages}
            >
                Selanjutnya
                <ArrowRight className="w-4 h-4 ml-2" />
            </Button>
        </div>
    );
};

// --- (BARU) Komponen Input Pencarian ---
const SearchInput = ({ initialValue, onSearch }) => {
    const [searchTerm, setSearchTerm] = useState(initialValue);
    const timeoutRef = useRef(null);

    // Debounce effect
    useEffect(() => {
        clearTimeout(timeoutRef.current);
        timeoutRef.current = setTimeout(() => {
            onSearch(searchTerm);
        }, 500); // 500ms delay

        return () => clearTimeout(timeoutRef.current);
    }, [searchTerm, onSearch]);

    return (
        <div className="relative mb-4">
            <input
                type="text"
                placeholder="Cari data..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full px-4 py-2 pl-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            />
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        </div>
    );
};

// --- API Client Sederhana (Tidak Berubah) ---
const wpData = window.umh_wp_data || {
    api_url: '/wp-json/umh/v1/', // Fallback
    nonce: '',
    user: { token: '' }
};

const api = {
    getToken: () => wpData.user ? wpData.user.token : null,
    request: async (endpoint, method = 'GET', body = null) => {
        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${api.getToken()}`
        };
        const config = {
            method: method,
            headers: headers,
        };
        if (body) {
            config.body = JSON.stringify(body);
        }
        try {
            const response = await fetch(wpData.api_url + endpoint, config);
            if (response.status === 204) {
                return { success: true };
            }
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || `Error ${response.status}: ${response.statusText}`);
            }
            return data;
        } catch (error) {
            console.error(`API Error (${method} ${endpoint}):`, error);
            throw error;
        }
    },
    get: (endpoint) => api.request(endpoint, 'GET'),
    post: (endpoint, body) => api.request(endpoint, 'POST', body),
    put: (endpoint, body) => api.request(endpoint, 'PUT', body),
    del: (endpoint) => api.request(endpoint, 'DELETE'),
};

// --- (PERBAIKAN TOTAL) Hook Kustom untuk CRUD (Paginasi & Search) ---
const useCRUD = (apiName, defaultFormState, onDependenciesFetched) => {
    const [list, setList] = useState([]);
    const [dependencies, setDependencies] = useState({});
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isModalOpen, setModalOpen] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [currentItem, setCurrentItem] = useState(null);
    const [formState, setFormState] = useState(defaultFormState);
    const [successMessage, setSuccessMessage] = useState(null);

    // State baru untuk paginasi dan pencarian
    const [page, setPage] = useState(1);
    const [searchTerm, setSearchTerm] = useState('');
    const [pagination, setPagination] = useState({
        total_items: 0,
        total_pages: 1,
        current_page: 1,
    });

    const showSuccess = (message) => {
        setSuccessMessage(message);
        setTimeout(() => setSuccessMessage(null), 3000);
    };

    const fetchDependencies = useCallback(async () => {
        if (onDependenciesFetched) {
            try {
                const deps = await onDependenciesFetched();
                setDependencies(deps || {});
            } catch (err) {
                setError(`Gagal memuat data pendukung: ${err.message}`);
            }
        }
    }, [onDependenciesFetched]);

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            // Selalu muat dependensi
            await fetchDependencies();
            
            // Bangun query URL dengan paginasi dan pencarian
            const query = new URLSearchParams({
                page: page,
                search: searchTerm,
            });
            
            const data = await api.get(`${apiName}?${query.toString()}`);
            
            // Logika baru untuk mem-parsing respon
            if (data && typeof data === 'object' && Array.isArray(data.data)) {
                // Respon paginasi dari UMH_CRUD_Controller
                setList(data.data);
                setPagination({
                    total_items: data.total_items,
                    total_pages: data.total_pages,
                    current_page: data.current_page,
                });
            } else if (Array.isArray(data)) {
                // Fallback untuk respon array (misal: API kustom lama)
                setList(data);
                setPagination({
                    total_items: data.length,
                    total_pages: 1,
                    current_page: 1,
                });
            } else {
                throw new Error("Format data tidak dikenal");
            }

        } catch (err) {
            setError(`Gagal memuat data ${apiName}: ${err.message}`);
        } finally {
            setIsLoading(false);
        }
    }, [apiName, fetchDependencies, page, searchTerm]);

    // useEffect utama kini bergantung pada fetchData
    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormState(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }));
    };

    const handleAddNew = () => {
        setIsEditing(false);
        setCurrentItem(null);
        setFormState(defaultFormState);
        setModalOpen(true);
    };

    const handleEdit = (item) => {
        setIsEditing(true);
        setCurrentItem(item);
        setFormState(item);
        setModalOpen(true);
    };

    const handleCloseModal = () => {
        setModalOpen(false);
        setError(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        try {
            let result;
            if (isEditing) {
                result = await api.put(`${apiName}/${currentItem.id}`, formState);
                showSuccess("Data berhasil diperbarui.");
            } else {
                result = await api.post(apiName, formState);
                showSuccess("Data berhasil ditambahkan.");
            }
            // Reset ke halaman 1 dan fetch ulang
            if (page !== 1) setPage(1);
            else fetchData();
            
            handleCloseModal();
        } catch (err) {
            setError(`Gagal menyimpan: ${err.message}`);
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm("Apakah Anda yakin ingin menghapus data ini?")) {
            return;
        }
        setError(null);
        try {
            await api.del(`${apiName}/${id}`);
            showSuccess("Data berhasil dihapus.");
            // Reset ke halaman 1 dan fetch ulang
            if (page !== 1) setPage(1);
            else fetchData();
        } catch (err) {
            setError(`Gagal menghapus: ${err.message}`);
        }
    };

    // Fungsi baru untuk menangani pencarian
    const handleSearch = (newSearchTerm) => {
        setSearchTerm(newSearchTerm);
        setPage(1); // Selalu reset ke halaman 1 saat mencari
    };

    return {
        list,
        dependencies,
        isLoading,
        error,
        successMessage,
        isModalOpen,
        isEditing,
        currentItem,
        formState,
        pagination, // Kirim info paginasi ke komponen
        searchTerm, // Kirim searchTerm
        setPage,    // Kirim setPage
        setSearchTerm: handleSearch, // Kirim fungsi search
        handleChange,
        handleAddNew,
        handleEdit,
        handleCloseModal,
        handleSubmit,
        handleDelete,
        fetchData, // Tetap kirim fetchData jika perlu refresh manual
        setError,
        setFormState,
        fetchDependencies,
    };
};
// --- Akhir Perbaikan useCRUD ---

// --- Komponen Tabel Generik (Tidak Berubah) ---
const CrudTable = ({ columns, data, onEdit, onDelete }) => (
    <div className="overflow-x-auto shadow-md rounded-lg">
        <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-100">
                <tr>
                    {columns.map((col) => (
                        <th key={col.key} className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {col.label}
                        </th>
                    ))}
                    <th className="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        Aksi
                    </th>
                </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
                {data.length === 0 ? (
                    <tr>
                        <td colSpan={columns.length + 1} className="px-6 py-4 text-center text-gray-500">
                            Tidak ada data.
                        </td>
                    </tr>
                ) : (
                    data.map((item) => (
                        <tr key={item.id} className="hover:bg-gray-50">
                            {columns.map((col) => (
                                <td key={col.key} className="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                    {col.render ? col.render(item) : item[col.key]}
                                </td>
                            ))}
                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button
                                    onClick={() => onEdit(item)}
                                    className="text-blue-600 hover:text-blue-800 mr-3"
                                >
                                    Edit
                                </button>
                                <button
                                    onClick={() => onDelete(item.id)}
                                    className="text-red-600 hover:text-red-800"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    ))
                )}
            </tbody>
        </table>
    </div>
);

// --- Komponen Halaman (Dashboard, Paket, dll.) ---

// 1. Dashboard (Tidak Berubah)
const StatCard = ({ title, value, icon, colorClass }) => (
    <div className={`bg-white p-6 rounded-lg shadow-lg border-l-4 ${colorClass.replace('text', 'border')} flex items-center space-x-4 transition-all hover:shadow-xl`}>
        <div className={`p-3 rounded-full ${colorClass} ${colorClass.replace('text', 'bg')}/10`}>
            {React.cloneElement(icon, { className: `w-6 h-6 ${colorClass}` })}
        </div>
        <div>
            <h3 className="text-sm font-medium text-gray-500 uppercase">{title}</h3>
            <p className="mt-1 text-3xl font-semibold text-gray-900">{value}</p>
        </div>
    </div>
);

const DashboardComponent = () => {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                setLoading(true);
                const data = await api.get('stats/totals');
                setStats(data);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };
        fetchStats();
    }, []);


    if (loading) return <Spinner />;
    if (error) return <Alert message={`Gagal memuat statistik: ${error}`} />;
    
    const { total_jamaah, total_packages, total_revenue } = stats || {};
    const total_jamaah_lunas = stats?.total_jamaah_lunas || 0;

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard 
                title="Total Jemaah" 
                value={total_jamaah || 0} 
                icon={<Users />} 
                colorClass="text-blue-600"
            />
            <StatCard 
                title="Total Paket" 
                value={total_packages || 0} 
                icon={<Package />}
                colorClass="text-indigo-600"
            />
            <StatCard 
                title="Total Pemasukan" 
                value={formatCurrency(total_revenue || 0)} 
                icon={<DollarSign />}
                colorClass="text-green-600"
            />
            <StatCard 
                title="Jemaah Lunas" 
                value={total_jamaah_lunas} 
                icon={<UserCheck />}
                colorClass="text-teal-600"
            />
        </div>
    );
};


// 2. Manajemen Paket (DITAMBAH PAGINASI & SEARCH)
const PackagesComponent = () => {
    const defaultState = {
        name: '',
        category_id: '',
        description: '',
        departure_date: '',
        duration_days: 0,
        status: 'draft',
        prices: [{ room_type: 'Quad', price: 0 }],
        flight_ids: [],
        hotel_bookings: [],
    };

    const fetchPackageDependencies = useCallback(async () => {
        // Optimasi: Hanya fetch dependensi jika list-nya kosong
        // Tapi useCRUD memanggil ini setiap fetch, jadi kita perlu memoize
        // Untuk sekarang, panggil saja setiap saat.
        const [categories, flights, hotels] = await Promise.all([
            api.get('categories?page=1&search='), // Asumsi kategori, flight, hotel tidak terlalu banyak
            api.get('flights?page=1&search='),
            api.get('hotels?page=1&search='),
        ]);
        return { 
            categories: categories.data || categories, 
            flights: flights.data || flights, 
            hotels: hotels.data || hotels 
        };
    }, []);

    const {
        list: packages,
        dependencies,
        isLoading,
        error,
        successMessage,
        isModalOpen,
        isEditing,
        formState,
        setFormState,
        pagination, // BARU
        searchTerm, // BARU
        setPage,    // BARU
        setSearchTerm, // BARU
        handleChange,
        handleAddNew,
        handleCloseModal,
        handleSubmit,
        handleDelete,
        fetchData
    } = useCRUD('packages', defaultState, fetchPackageDependencies);

    // --- State untuk Form Dinamis (Tidak Berubah) ---
    const handlePriceChange = (index, field, value) => {
        const newPrices = [...formState.prices];
        newPrices[index][field] = value;
        setFormState(prev => ({ ...prev, prices: newPrices }));
    };
    const addPriceField = () => {
        setFormState(prev => ({
            ...prev,
            prices: [...prev.prices, { room_type: '', price: 0 }]
        }));
    };
    const removePriceField = (index) => {
        const newPrices = formState.prices.filter((_, i) => i !== index);
        setFormState(prev => ({ ...prev, prices: newPrices }));
    };
    const handleFlightChange = (e) => {
        const selectedIds = Array.from(e.target.selectedOptions, option => parseInt(option.value));
        setFormState(prev => ({ ...prev, flight_ids: selectedIds }));
    };
    const handleHotelBookingChange = (index, field, value) => {
        const newBookings = [...formState.hotel_bookings];
        newBookings[index][field] = value;
        setFormState(prev => ({ ...prev, hotel_bookings: newBookings }));
    };
    const addHotelField = () => {
        setFormState(prev => ({
            ...prev,
            hotel_bookings: [...prev.hotel_bookings, { hotel_id: '', check_in_date: '', check_out_date: '' }]
        }));
    };
    const removeHotelField = (index) => {
        const newBookings = formState.hotel_bookings.filter((_, i) => i !== index);
        setFormState(prev => ({ ...prev, hotel_bookings: newBookings }));
    };
    
    // --- Override handleEdit (Tidak Berubah) ---
    const handleEdit = (item) => {
        setIsEditing(true);
        setCurrentItem(item);
        setFormState({
            ...item,
            prices: item.prices || [],
            flight_ids: item.flight_ids || [],
            hotel_bookings: item.hotel_bookings || [],
        });
        setModalOpen(true);
    };

    const columns = [
        { key: 'name', label: 'Nama Paket' },
        { 
            key: 'category', 
            label: 'Kategori',
            render: (item) => dependencies.categories?.find(c => c.id == item.category_id)?.name || 'N/A'
        },
        { 
            key: 'price', 
            label: 'Harga',
            render: (item) => {
                if (!item.prices || item.prices.length === 0) return 'Rp 0';
                const minPrice = Math.min(...item.prices.map(p => p.price));
                return `Mulai dari ${formatCurrency(minPrice)}`;
            }
        },
        { 
            key: 'departure_date', 
            label: 'Keberangkatan',
            render: (item) => formatDateForInput(item.departure_date)
        },
        { key: 'duration_days', label: 'Durasi (Hari)' },
        { key: 'status', label: 'Status' },
    ];

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Paket</h2>
                <Button onClick={handleAddNew}>Tambah Paket Baru</Button>
            </div>

            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable
                        columns={columns}
                        data={packages}
                        onEdit={handleEdit}
                        onDelete={handleDelete}
                    />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}

            <Modal
                show={isModalOpen}
                onClose={handleCloseModal}
                title={isEditing ? "Edit Paket" : "Tambah Paket Baru"}
                footer={
                    <>
                        <Button onClick={handleCloseModal} variant="secondary" className="mr-2">Batal</Button>
                        <Button onClick={handleSubmit} type="submit" form="package-form">Simpan</Button>
                    </>
                }
            >
                <form id="package-form" onSubmit={handleSubmit}>
                    <FormInput label="Nama Paket" name="name" value={formState.name} onChange={handleChange} required />
                    <FormSelect label="Kategori" name="category_id" value={formState.category_id} onChange={handleChange} required>
                        <option value="">Pilih Kategori</option>
                        {dependencies.categories?.map(cat => (
                            <option key={cat.id} value={cat.id}>{cat.name}</option>
                        ))}
                    </FormSelect>
                    <FormTextarea label="Deskripsi" name="description" value={formState.description} onChange={handleChange} />
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Tanggal Keberangkatan" name="departure_date" type="date" value={formatDateForInput(formState.departure_date)} onChange={handleChange} required />
                        <FormInput label="Durasi (hari)" name="duration_days" type="number" value={formState.duration_days} onChange={handleChange} required />
                    </div>
                    <FormSelect label="Status" name="status" value={formState.status} onChange={handleChange} required>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Archived</option>
                    </FormSelect>
                    
                    <div className="p-4 border border-gray-200 rounded-md mt-6">
                        <h4 className="font-semibold text-gray-700 mb-3">Harga Dinamis</h4>
                        {formState.prices.map((priceItem, index) => (
                            <div key={index} className="flex items-center gap-2 mb-2">
                                <input
                                    type="text"
                                    placeholder="Tipe Kamar (e.g., Quad)"
                                    value={priceItem.room_type}
                                    onChange={(e) => handlePriceChange(index, 'room_type', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                                />
                                <input
                                    type="number"
                                    placeholder="Harga"
                                    value={priceItem.price}
                                    onChange={(e) => handlePriceChange(index, 'price', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                                />
                                <Button type="button" variant="danger" onClick={() => removePriceField(index)}>X</Button>
                            </div>
                        ))}
                        <Button type="button" variant="outline" onClick={addPriceField}>+ Tambah Harga</Button>
                    </div>

                    <div className="p-4 border border-gray-200 rounded-md mt-6">
                        <h4 className="font-semibold text-gray-700 mb-1">Link Pesawat</h4>
                        <p className="text-sm text-gray-500 mb-3">Tahan Ctrl/Cmd untuk memilih lebih dari satu.</p>
                        <select
                            multiple
                            name="flight_ids"
                            value={formState.flight_ids}
                            onChange={handleFlightChange}
                            className="w-full h-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                        >
                            {dependencies.flights?.map(flight => (
                                <option key={flight.id} value={flight.id}>
                                    {flight.airline} ({flight.flight_number}) - {flight.departure_airport_code} ke {flight.arrival_airport_code}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="p-4 border border-gray-200 rounded-md mt-6">
                        <h4 className="font-semibold text-gray-700 mb-3">Link Hotel</h4>
                        {formState.hotel_bookings.map((booking, index) => (
                            <div key={index} className="grid grid-cols-3 gap-2 mb-2 p-2 border rounded">
                                <select
                                    value={booking.hotel_id}
                                    onChange={(e) => handleHotelBookingChange(index, 'hotel_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white"
                                >
                                    <option value="">Pilih Hotel</option>
                                    {dependencies.hotels?.map(hotel => (
                                        <option key={hotel.id} value={hotel.id}>{hotel.name} ({hotel.city})</option>
                                    ))}
                                </select>
                                <input
                                    type="date"
                                    placeholder="Check-in"
                                    value={formatDateForInput(booking.check_in_date)}
                                    onChange={(e) => handleHotelBookingChange(index, 'check_in_date', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                                />
                                <div className="flex">
                                    <input
                                        type="date"
                                        placeholder="Check-out"
                                        value={formatDateForInput(booking.check_out_date)}
                                        onChange={(e) => handleHotelBookingChange(index, 'check_out_date', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm"
                                    />
                                    <Button type="button" variant="danger" onClick={() => removeHotelField(index)} className="ml-2">X</Button>
                                </div>
                            </div>
                        ))}
                        <Button type="button" variant="outline" onClick={addHotelField}>+ Tambah Hotel</Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
};


// 3. Manajemen Jemaah (DITAMBAH PAGINASI & SEARCH)
const JamaahComponent = () => {
    const defaultState = {
        full_name: '',
        package_id: '',
        birth_date: '',
        gender: 'Laki-laki',
        address: '',
        phone: '',
        email: '',
        passport_number: '',
        passport_expiry: '',
        ktp_number: '',
        room_type: 'Quad',
        total_price: 0,
        status: 'pending',
        notes: '',
    };

    const fetchJamaahDependencies = useCallback(async () => {
        const packagesData = await api.get('packages?page=1&search='); // Ambil semua paket (asumsi tidak terlalu banyak)
        return { packages: packagesData.data || packagesData };
    }, []);

    const {
        list: jamaahList,
        dependencies,
        isLoading,
        error,
        successMessage,
        isModalOpen,
        isEditing,
        currentItem,
        formState,
        pagination, // BARU
        searchTerm, // BARU
        setPage,    // BARU
        setSearchTerm, // BARU
        handleChange,
        handleAddNew,
        // handleEdit, // Kita akan override
        handleCloseModal,
        handleSubmit,
        handleDelete,
        fetchData,
        setError,
        setFormState,
    } = useCRUD('jamaah', defaultState, fetchJamaahDependencies);

    // --- State & Fungsi Lokal Jemaah (Uploads, Payments) ---
    const [ktpFile, setKtpFile] = useState(null);
    const [passportFile, setPassportFile] = useState(null);
    const [payments, setPayments] = useState([]);
    const [isPaymentModalOpen, setPaymentModalOpen] = useState(false);
    const [currentPayment, setCurrentPayment] = useState(null);
    const [isPaymentLoading, setPaymentLoading] = useState(false);
    const [paymentError, setPaymentError] = useState(null);

    const handleFileUpload = async (file, uploadType, jamaahId) => {
        if (!file) return;
        const formData = new FormData();
        formData.append('file', file);
        formData.append('jamaah_id', jamaahId);
        formData.append('upload_type', uploadType);

        try {
            const token = api.getToken();
            const response = await fetch(`${wpData.api_url}uploads`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` },
                body: formData
            });
            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.message || 'Upload gagal');
            }
            const result = await response.json();
            setFormState(prev => ({ ...prev, [uploadType]: result.file_url }));
            fetchData();
            if (uploadType === 'ktp_scan') setKtpFile(null);
            if (uploadType === 'passport_scan') setPassportFile(null);
        } catch (err) {
            setError(`Upload ${uploadType} gagal: ${err.message}`);
        }
    };

    const fetchPayments = async (jamaahId) => {
        if (!jamaahId) return;
        setPaymentLoading(true);
        setPaymentError(null);
        try {
            const data = await api.get(`payments?jamaah_id=${jamaahId}`);
            setPayments(data);
        } catch (err) {
            setPaymentError(`Gagal memuat pembayaran: ${err.message}`);
        } finally {
            setPaymentLoading(false);
        }
    };

    // Override handleEdit dari useCRUD
    const handleEdit = (item) => {
        setIsEditing(true);
        setCurrentItem(item);
        setFormState(item);
        setModalOpen(true);
        // Panggil fetch payments
        fetchPayments(item.id);
    };

    const handleCloseMainModal = () => {
        handleCloseModal();
        setPayments([]);
        setPaymentError(null);
        setKtpFile(null);
        setPassportFile(null);
    };

    const handleOpenPaymentModal = (payment = null) => {
        setCurrentPayment(payment);
        setPaymentModalOpen(true);
    };

    const handleClosePaymentModal = () => {
        setCurrentPayment(null);
        setPaymentModalOpen(false);
        setPaymentError(null);
    };

    const handleSavePayment = async (paymentData, proofFile) => {
        setPaymentLoading(true);
        setPaymentError(null);
        try {
            let savedPayment;
            const paymentPayload = { ...paymentData, jamaah_id: currentItem.id };
            if (currentPayment) {
                savedPayment = await api.put(`payments/${currentPayment.id}`, paymentPayload);
            } else {
                savedPayment = await api.post('payments', paymentPayload);
            }
            if (proofFile) {
                await handleProofUpload(savedPayment.id, proofFile);
            }
            await fetchPayments(currentItem.id);
            await fetchData();
            handleClosePaymentModal();
        } catch (err) {
            setPaymentError(`Gagal menyimpan pembayaran: ${err.message}`);
        } finally {
            setPaymentLoading(false);
        }
    };

    const handleProofUpload = async (paymentId, file) => {
        const formData = new FormData();
        formData.append('file', file);
        const token = api.getToken();
        const response = await fetch(`${wpData.api_url}payments/${paymentId}/upload_proof`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: formData
        });
        if (!response.ok) {
            const errData = await response.json();
            throw new Error(errData.message || 'Upload bukti gagal');
        }
    };

    const handleDeletePayment = async (paymentId) => {
        if (!window.confirm("Yakin ingin menghapus pembayaran ini?")) return;
        setPaymentLoading(true);
        setPaymentError(null);
        try {
            await api.del(`payments/${paymentId}`);
            await fetchPayments(currentItem.id);
            await fetchData();
        } catch (err) {
            setPaymentError(`Gagal menghapus pembayaran: ${err.message}`);
        } finally {
            setPaymentLoading(false);
        }
    };

    const columns = [
        { key: 'full_name', label: 'Nama Jemaah' },
        { 
            key: 'package', 
            label: 'Paket',
            render: (item) => dependencies.packages?.find(p => p.id == item.package_id)?.name || 'N/A'
        },
        { key: 'phone', label: 'Telepon' },
        { key: 'email', label: 'Email' },
        { 
            key: 'payment_status', 
            label: 'Status Bayar',
            render: (item) => {
                const status = item.payment_status || 'Belum Lunas';
                const colors = {
                    'Lunas': 'bg-green-100 text-green-800',
                    'Cicil': 'bg-yellow-100 text-yellow-800',
                    'Belum Lunas': 'bg-red-100 text-red-800',
                };
                return <span className={`px-2 py-1 rounded-full text-xs font-medium ${colors[status]}`}>{status}</span>;
            }
        },
        { 
            key: 'amount_paid', 
            label: 'Total Bayar',
            render: (item) => formatCurrency(item.amount_paid)
        },
        { key: 'status', label: 'Status Jemaah' },
    ];

    const getPackagePriceInfo = (pkg) => {
        if (!pkg.prices || pkg.prices.length === 0) return "(Harga belum diatur)";
        const minPrice = Math.min(...pkg.prices.map(p => p.price));
        return `(Mulai dari ${formatCurrency(minPrice)})`;
    };

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Jemaah (Manifest)</h2>
                <Button onClick={handleAddNew}>Tambah Jemaah Baru</Button>
            </div>

            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable
                        columns={columns}
                        data={jamaahList}
                        onEdit={handleEdit}
                        onDelete={handleDelete}
                    />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}

            <Modal
                show={isModalOpen}
                onClose={handleCloseMainModal}
                title={isEditing ? "Edit Jemaah" : "Tambah Jemaah Baru"}
                footer={
                    <>
                        <Button onClick={handleCloseMainModal} variant="secondary" className="mr-2">Batal</Button>
                        <Button onClick={handleSubmit} type="submit" form="jamaah-form">Simpan Jemaah</Button>
                    </>
                }
            >
                <Alert message={error} type="error" />
                <form id="jamaah-form" onSubmit={handleSubmit} className="space-y-4">
                    <FormInput label="Nama Lengkap" name="full_name" value={formState.full_name} onChange={handleChange} required />
                    <FormSelect label="Paket" name="package_id" value={formState.package_id} onChange={handleChange} required>
                        <option value="">Pilih Paket</option>
                        {dependencies.packages?.map(pkg => (
                            <option key={pkg.id} value={pkg.id}>
                                {pkg.name} {getPackagePriceInfo(pkg)}
                            </option>
                        ))}
                    </FormSelect>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Tanggal Lahir" name="birth_date" type="date" value={formatDateForInput(formState.birth_date)} onChange={handleChange} />
                        <FormSelect label="Jenis Kelamin" name="gender" value={formState.gender} onChange={handleChange}>
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </FormSelect>
                    </div>
                    <FormTextarea label="Alamat" name="address" value={formState.address} onChange={handleChange} />
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Telepon" name="phone" value={formState.phone} onChange={handleChange} required />
                        <FormInput label="Email" name="email" type="email" value={formState.email} onChange={handleChange} />
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="No. KTP" name="ktp_number" value={formState.ktp_number} onChange={handleChange} />
                        <FormInput label="No. Paspor" name="passport_number" value={formState.passport_number} onChange={handleChange} />
                    </div>
                    <FormInput label="Tgl Kadaluarsa Paspor" name="passport_expiry" type="date" value={formatDateForInput(formState.passport_expiry)} onChange={handleChange} />
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSelect label="Tipe Kamar" name="room_type" value={formState.room_type} onChange={handleChange}>
                            <option value="Quad">Quad (4)</option>
                            <option value="Triple">Triple (3)</option>
                            <option value="Double">Double (2)</option>
                        </FormSelect>
                        <FormInput label="Total Harga Paket" name="total_price" type="number" value={formState.total_price} onChange={handleChange} required />
                    </div>
                    
                    <FormSelect label="Status Jemaah" name="status" value={formState.status} onChange={handleChange} required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="cancelled">Cancelled</option>
                    </FormSelect>
                    <FormTextarea label="Catatan" name="notes" value={formState.notes} onChange={handleChange} />

                    {isEditing && (
                        <>
                            <div className="p-4 border border-gray-200 rounded-md mt-6">
                                <h4 className="font-semibold text-gray-700 mb-3">Upload Dokumen</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <FormInput 
                                            label={`Upload KTP (File: ${formState.ktp_scan ? 'Ada' : 'Kosong'})`} 
                                            type="file" 
                                            onChange={(e) => setKtpFile(e.target.files[0])} 
                                        />
                                        <Button 
                                            type="button" 
                                            onClick={() => handleFileUpload(ktpFile, 'ktp_scan', currentItem.id)}
                                            disabled={!ktpFile}
                                            className="mt-2"
                                            variant="outline"
                                        >
                                            Upload KTP
                                        </Button>
                                    </div>
                                    <div>
                                        <FormInput 
                                            label={`Upload Paspor (File: ${formState.passport_scan ? 'Ada' : 'Kosong'})`} 
                                            type="file" 
                                            onChange={(e) => setPassportFile(e.target.files[0])}
                                        />
                                        <Button 
                                            type="button" 
                                            onClick={() => handleFileUpload(passportFile, 'passport_scan', currentItem.id)}
                                            disabled={!passportFile}
                                            className="mt-2"
                                            variant="outline"
                                        >
                                            Upload Paspor
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div className="p-4 border border-gray-200 rounded-md mt-6">
                                <div className="flex justify-between items-center mb-3">
                                    <h4 className="font-semibold text-gray-700">Riwayat Pembayaran</h4>
                                    <Button type="button" variant="primary" onClick={() => handleOpenPaymentModal(null)}>
                                        Tambah Pembayaran
                                    </Button>
                                </div>
                                <Alert message={paymentError} type="error" />
                                {isPaymentLoading && !payments.length ? <Spinner /> : (
                                    <div className="max-h-60 overflow-y-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tgl</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tahap</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {payments.length === 0 ? (
                                                    <tr><td colSpan="5" className="text-center py-4 text-sm text-gray-500">Belum ada pembayaran.</td></tr>
                                                ) : (
                                                    payments.map(p => (
                                                        <tr key={p.id}>
                                                            <td className="px-3 py-2 text-sm">{formatDateForInput(p.payment_date)}</td>
                                                            <td className="px-3 py-2 text-sm">{formatCurrency(p.amount)}</td>
                                                            <td className="px-3 py-2 text-sm">{p.payment_stage}</td>
                                                            <td className="px-3 py-2 text-sm">
                                                                <span className={`px-2 py-0.5 rounded-full text-xs ${p.status === 'verified' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}>
                                                                    {p.status}
                                                                </span>
                                                            </td>
                                                            <td className="px-3 py-2 text-sm text-right">
                                                                <button type="button" onClick={() => handleOpenPaymentModal(p)} className="text-blue-600 hover:text-blue-800 mr-2">Edit</button>
                                                                <button type="button" onClick={() => handleDeletePayment(p.id)} className="text-red-600 hover:text-red-800">Hapus</button>
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </form>
            </Modal>

            {isPaymentModalOpen && (
                <PaymentModal
                    show={isPaymentModalOpen}
                    onClose={handleClosePaymentModal}
                    payment={currentPayment}
                    onSave={handleSavePayment}
                    isLoading={isPaymentLoading}
                    error={paymentError}
                />
            )}
        </div>
    );
};

// --- PaymentModal (Tidak Berubah) ---
const PaymentModal = ({ show, onClose, payment, onSave, isLoading, error }) => {
    const [form, setForm] = useState({
        payment_date: formatDateForInput(new Date()),
        amount: 0,
        payment_stage: 'DP 1',
        status: 'pending',
        notes: '',
        proof_url: '',
    });
    const [proofFile, setProofFile] = useState(null);

    useEffect(() => {
        if (payment) {
            setForm({
                payment_date: formatDateForInput(payment.payment_date),
                amount: payment.amount,
                payment_stage: payment.payment_stage,
                status: payment.status,
                notes: payment.notes || '',
                proof_url: payment.proof_url || '',
            });
        } else {
            setForm({
                payment_date: formatDateForInput(new Date()),
                amount: 0,
                payment_stage: 'DP 1',
                status: 'pending',
                notes: '',
                proof_url: '',
            });
        }
        setProofFile(null);
    }, [payment, show]);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setForm(prev => ({ ...prev, [name]: value }));
    };

    const handleFileChange = (e) => {
        setProofFile(e.target.files[0]);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        onSave(form, proofFile);
    };

    return (
        <Modal
            show={show}
            onClose={onClose}
            title={payment ? "Edit Pembayaran" : "Tambah Pembayaran Baru"}
            footer={
                <>
                    <Button onClick={onClose} variant="secondary" className="mr-2" disabled={isLoading}>Batal</Button>
                    <Button onClick={handleSubmit} type="submit" form="payment-form" disabled={isLoading}>
                        {isLoading ? "Menyimpan..." : "Simpan Pembayaran"}
                    </Button>
                </>
            }
        >
            <Alert message={error} type="error" />
            <form id="payment-form" onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput label="Tanggal Bayar" name="payment_date" type="date" value={form.payment_date} onChange={handleChange} required />
                    <FormInput label="Jumlah Bayar" name="amount" type="number" value={form.amount} onChange={handleChange} required />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput label="Tahap Pembayaran" name="payment_stage" value={form.payment_stage} onChange={handleChange} placeholder="e.g., DP 1, Cicilan 2, Pelunasan" />
                    <FormSelect label="Status" name="status" value={form.status} onChange={handleChange} required>
                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </FormSelect>
                </div>
                <FormTextarea label="Catatan" name="notes" value={form.notes} onChange={handleChange} />
                <FormInput 
                    label="Upload Bukti Pembayaran" 
                    type="file" 
                    onChange={handleFileChange} 
                />
                {form.proof_url && (
                    <p className="text-sm text-gray-600">
                        Bukti saat ini: <a href={form.proof_url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">Lihat File</a>
                    </p>
                )}
            </form>
        </Modal>
    );
};


// 4. Manajemen Keuangan (DITAMBAH PAGINASI & SEARCH)
const FinanceComponent = () => {
    const defaultState = {
        transaction_date: formatDateForInput(new Date()),
        type: 'expense',
        category: '',
        description: '',
        amount: 0,
    };

    const {
        list: transactions,
        isLoading,
        error,
        successMessage,
        isModalOpen,
        isEditing,
        formState,
        pagination, // BARU
        searchTerm, // BARU
        setPage,    // BARU
        setSearchTerm, // BARU
        handleChange,
        handleAddNew,
        handleEdit,
        handleCloseModal,
        handleSubmit,
        handleDelete
    } = useCRUD('finance', defaultState);

    const columns = [
        { 
            key: 'transaction_date', 
            label: 'Tanggal',
            render: (item) => formatDateForInput(item.transaction_date)
        },
        { 
            key: 'type', 
            label: 'Tipe',
            render: (item) => (
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${item.type === 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                    {item.type}
                </span>
            )
        },
        { key: 'category', label: 'Kategori' },
        { key: 'description', label: 'Deskripsi' },
        { 
            key: 'amount', 
            label: 'Jumlah',
            render: (item) => formatCurrency(item.amount)
        },
    ];

    const balance = useMemo(() => {
        // NOTE: Saldo ini hanya saldo dari halaman yang ditampilkan.
        // Untuk saldo total, idealnya API stats/totals harus diperbarui.
        return transactions.reduce((acc, trx) => {
            if (trx.type === 'income') return acc + parseFloat(trx.amount);
            if (trx.type === 'expense') return acc - parseFloat(trx.amount);
            return acc;
        }, 0);
    }, [transactions]); // Seharusnya ini mengambil dari API stats

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Keuangan</h2>
                <div className="flex items-center space-x-4">
                    {/* <div className="text-right">
                        <span className="text-sm text-gray-500">Saldo (Halaman Ini)</span>
                        <p className={`text-xl font-semibold ${balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            {formatCurrency(balance)}
                        </p>
                    </div> */}
                    <Button onClick={handleAddNew}>Tambah Transaksi</Button>
                </div>
            </div>

            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable
                        columns={columns}
                        data={transactions}
                        onEdit={handleEdit}
                        onDelete={handleDelete}
                    />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}

            <Modal
                show={isModalOpen}
                onClose={handleCloseModal}
                title={isEditing ? "Edit Transaksi" : "Tambah Transaksi Baru"}
                footer={
                    <>
                        <Button onClick={handleCloseModal} variant="secondary" className="mr-2">Batal</Button>
                        <Button onClick={handleSubmit} type="submit" form="finance-form">Simpan</Button>
                    </>
                }
            >
                <form id="finance-form" onSubmit={handleSubmit}>
                    <FormInput label="Tanggal" name="transaction_date" type="date" value={formatDateForInput(formState.transaction_date)} onChange={handleChange} required />
                    <FormSelect label="Tipe Transaksi" name="type" value={formState.type} onChange={handleChange} required>
                        <option value="expense">Pengeluaran (Expense)</option>
                        <option value="income">Pemasukan (Income)</option>
                    </FormSelect>
                    <FormInput label="Kategori" name="category" value={formState.category} onChange={handleChange} placeholder="e.g., Tiket Pesawat, Akomodasi, Gaji" />
                    <FormInput label="Jumlah" name="amount" type="number" value={formState.amount} onChange={handleChange} required />
                    <FormTextarea label="Deskripsi" name="description" value={formState.description} onChange={handleChange} />
                </form>
            </Modal>
        </div>
    );
};

// 5. Manajemen Tugas (DITAMBAH PAGINASI & SEARCH)
const TasksComponent = () => {
    const defaultState = {
        title: '',
        description: '',
        assigned_to_user_id: '',
        jamaah_id: '',
        due_date: '',
        status: 'pending',
        priority: 'medium',
    };

    const fetchTaskDependencies = useCallback(async () => {
        const [usersData, jamaahData] = await Promise.all([
            api.get('users?page=1&search='),
            api.get('jamaah?page=1&search=')
        ]);
        return { 
            users: usersData.data || usersData, 
            jamaah: jamaahData.data || jamaahData 
        };
    }, []);

    const {
        list: tasks,
        dependencies,
        isLoading,
        error,
        successMessage,
        isModalOpen,
        isEditing,
        formState,
        pagination, // BARU
        searchTerm, // BARU
        setPage,    // BARU
        setSearchTerm, // BARU
        handleChange,
        handleAddNew,
        handleEdit,
        handleCloseModal,
        handleSubmit,
        handleDelete
    } = useCRUD('tasks', defaultState, fetchTaskDependencies);

    const columns = [
        { key: 'title', label: 'Judul Tugas' },
        { 
            key: 'assigned_to', 
            label: 'Ditugaskan Kepada',
            render: (item) => dependencies.users?.find(u => u.id == item.assigned_to_user_id)?.full_name || 'N/A'
        },
        { 
            key: 'jamaah', 
            label: 'Terkait Jemaah',
            render: (item) => dependencies.jamaah?.find(j => j.id == item.jamaah_id)?.full_name || 'N/A'
        },
        { 
            key: 'due_date', 
            label: 'Batas Waktu',
            render: (item) => formatDateForInput(item.due_date)
        },
        { key: 'priority', label: 'Prioritas' },
        { key: 'status', label: 'Status' },
    ];

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Tugas</h2>
                <Button onClick={handleAddNew}>Tambah Tugas Baru</Button>
            </div>

            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable
                        columns={columns}
                        data={tasks}
                        onEdit={handleEdit}
                        onDelete={handleDelete}
                    />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}

            <Modal
                show={isModalOpen}
                onClose={handleCloseModal}
                title={isEditing ? "Edit Tugas" : "Tambah Tugas Baru"}
                footer={
                    <>
                        <Button onClick={handleCloseModal} variant="secondary" className="mr-2">Batal</Button>
                        <Button onClick={handleSubmit} type="submit" form="task-form">Simpan</Button>
                    </>
                }
            >
                <form id="task-form" onSubmit={handleSubmit}>
                    <FormInput label="Judul Tugas" name="title" value={formState.title} onChange={handleChange} required />
                    <FormTextarea label="Deskripsi" name="description" value={formState.description} onChange={handleChange} />
                    <FormSelect label="Tugaskan Kepada" name="assigned_to_user_id" value={formState.assigned_to_user_id} onChange={handleChange}>
                        <option value="">Pilih Staff</option>
                        {dependencies.users?.map(user => (
                            <option key={user.id} value={user.id}>{user.full_name}</option>
                        ))}
                    </FormSelect>
                    <FormSelect label="Terkait Jemaah (Opsional)" name="jamaah_id" value={formState.jamaah_id} onChange={handleChange}>
                        <option value="">Pilih Jemaah</option>
                        {dependencies.jamaah?.map(j => (
                            <option key={j.id} value={j.id}>{j.full_name}</option>
                        ))}
                    </FormSelect>
                    <FormInput label="Batas Waktu" name="due_date" type="date" value={formatDateForInput(formState.due_date)} onChange={handleChange} />
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSelect label="Prioritas" name="priority" value={formState.priority} onChange={handleChange}>
                            <option value="low">Rendah</option>
                            <option value="medium">Sedang</option>
                            <option value="high">Tinggi</option>
                        </FormSelect>
                        <FormSelect label="Status" name="status" value={formState.status} onChange={handleChange}>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </FormSelect>
                    </div>
                </form>
            </Modal>
        </div>
    );
};

// 6. Manajemen Pengguna (Staff) (DITAMBAH PAGINASI & SEARCH)
const UsersComponent = () => {
    const defaultState = {
        email: '',
        full_name: '',
        role: 'agent',
        phone: '',
        status: 'active',
        password: '',
    };

    const fetchUserDependencies = useCallback(async () => {
        const rolesData = await api.get('roles?page=1&search=');
        return { roles: rolesData.data || rolesData };
    }, []);

    const {
        list: userList,
        dependencies,
        isLoading,
        error,
        successMessage,
        isModalOpen,
        isEditing,
        formState,
        pagination, // BARU
        searchTerm, // BARU
        setPage,    // BARU
        setSearchTerm, // BARU
        handleChange,
        handleAddNew,
        handleEdit,
        handleCloseModal,
        handleSubmit,
        handleDelete
    } = useCRUD('users', defaultState, fetchUserDependencies);

    const columns = [
        { key: 'full_name', label: 'Nama Lengkap' },
        { key: 'email', label: 'Email (Login)' },
        { 
            key: 'role', 
            label: 'Role',
            render: (item) => dependencies.roles?.find(r => r.role_key === item.role)?.role_name || item.role
        },
        { key: 'phone', label: 'Telepon' },
        { key: 'status', label: 'Status' },
    ];

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Pengguna (Staff)</h2>
                <Button onClick={handleAddNew}>Tambah Staff Baru</Button>
            </div>

            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable
                        columns={columns}
                        data={userList}
                        onEdit={handleEdit}
                        onDelete={handleDelete}
                    />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}

            <Modal
                show={isModalOpen}
                onClose={handleCloseModal}
                title={isEditing ? "Edit Staff" : "Tambah Staff Baru"}
                footer={
                    <>
                        <Button onClick={handleCloseModal} variant="secondary" className="mr-2">Batal</Button>
                        <Button onClick={handleSubmit} type="submit" form="user-form">Simpan</Button>
                    </>
                }
            >
                <form id="user-form" onSubmit={handleSubmit}>
                    <FormInput label="Nama Lengkap" name="full_name" value={formState.full_name} onChange={handleChange} required />
                    <FormInput 
                        label="Email (Untuk Login)" 
                        name="email" 
                        type="email" 
                        value={formState.email} 
                        onChange={handleChange} 
                        required 
                        disabled={isEditing}
                    />
                    {isEditing ? (
                        <FormInput label="Password" name="password" type="password" value={formState.password} onChange={handleChange} placeholder="Kosongkan jika tidak ingin mengubah" />
                    ) : (
                        <FormInput label="Password" name="password" type="password" value={formState.password} onChange={handleChange} required />
                    )}
                    <FormInput label="Telepon" name="phone" value={formState.phone} onChange={handleChange} />
                    
                    <FormSelect label="Role" name="role" value={formState.role} onChange={handleChange} required>
                        <option value="">Pilih Role</option>
                        {dependencies.roles?.map(role => (
                            <option key={role.id} value={role.role_key}>{role.role_name}</option>
                        ))}
                    </FormSelect>
                    
                    <FormSelect label="Status" name="status" value={formState.status} onChange={handleChange}>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </FormSelect>
                </form>
            </Modal>
        </div>
    );
};

// 7. Manajemen Kategori (DITAMBAH PAGINASI & SEARCH)
const CategoriesComponent = () => {
    const defaultState = { name: '', description: '' };
    const {
        list, isLoading, error, successMessage, isModalOpen, isEditing,
        formState, pagination, searchTerm, setPage, setSearchTerm, // BARU
        handleChange, handleAddNew, handleEdit,
        handleCloseModal, handleSubmit, handleDelete
    } = useCRUD('categories', defaultState);

    const columns = [
        { key: 'name', label: 'Nama Kategori' },
        { key: 'description', label: 'Deskripsi' },
    ];

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Kategori Paket</h2>
                <Button onClick={handleAddNew}>Tambah Kategori</Button>
            </div>
            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable columns={columns} data={list} onEdit={handleEdit} onDelete={handleDelete} />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}
            <Modal
                show={isModalOpen} onClose={handleCloseModal}
                title={isEditing ? "Edit Kategori" : "Tambah Kategori"}
                footer={<>
                    <Button onClick={handleCloseModal} variant="secondary" className="mr-2">Batal</Button>
                    <Button onClick={handleSubmit} type="submit" form="category-form">Simpan</Button>
                </>}
            >
                <form id="category-form" onSubmit={handleSubmit}>
                    <FormInput label="Nama Kategori" name="name" value={formState.name} onChange={handleChange} required />
                    <FormTextarea label="Deskripsi" name="description" value={formState.description} onChange={handleChange} />
                </form>
            </Modal>
        </div>
    );
};

// 8. Manajemen Pesawat (DITAMBAH PAGINASI & SEARCH)
const FlightsComponent = () => {
    const defaultState = {
        airline: '',
        flight_number: '',
        departure_airport_code: '',
        arrival_airport_code: '',
        departure_time: '',
        arrival_time: '',
        cost_per_seat: 0,
        total_seats: 0,
    };
    const {
        list, isLoading, error, successMessage, isModalOpen, isEditing,
        formState, pagination, searchTerm, setPage, setSearchTerm, // BARU
        handleChange, handleAddNew, handleEdit,
        handleCloseModal, handleSubmit, handleDelete
    } = useCRUD('flights', defaultState);

    const columns = [
        { key: 'airline', label: 'Maskapai' },
        { key: 'flight_number', label: 'No. Penerbangan' },
        { key: 'departure_airport_code', label: 'Dari' },
        { key: 'arrival_airport_code', label: 'Ke' },
        { key: 'departure_time', label: 'Waktu Berangkat', render: (item) => new Date(item.departure_time).toLocaleString('id-ID') },
        { key: 'total_seats', label: 'Kapasitas' },
    ];

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Data Pesawat</h2>
                <Button onClick={handleAddNew}>Tambah Data Pesawat</Button>
            </div>
            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />
            
            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable columns={columns} data={list} onEdit={handleEdit} onDelete={handleDelete} />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}
            <Modal
                show={isModalOpen} onClose={handleCloseModal}
                title={isEditing ? "Edit Data Pesawat" : "Tambah Data Pesawat"}
                footer={<>
                    <Button onClick={handleCloseModal} variant="secondary" className="mr-2">Batal</Button>
                    <Button onClick={handleSubmit} type="submit" form="flight-form">Simpan</Button>
                </>}
            >
                <form id="flight-form" onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Maskapai" name="airline" value={formState.airline} onChange={handleChange} required />
                        <FormInput label="No. Penerbangan" name="flight_number" value={formState.flight_number} onChange={handleChange} required />
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Kode Airport (Berangkat)" name="departure_airport_code" value={formState.departure_airport_code} onChange={handleChange} required placeholder="e.g., JED" />
                        <FormInput label="Kode Airport (Tiba)" name="arrival_airport_code" value={formState.arrival_airport_code} onChange={handleChange} required placeholder="e.g., CGK" />
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Waktu Berangkat" name="departure_time" type="datetime-local" value={formState.departure_time} onChange={handleChange} required />
                        <FormInput label="Waktu Tiba" name="arrival_time" type="datetime-local" value={formState.arrival_time} onChange={handleChange} required />
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Biaya per Kursi" name="cost_per_seat" type="number" value={formState.cost_per_seat} onChange={handleChange} />
                        <FormInput label="Total Kursi" name="total_seats" type="number" value={formState.total_seats} onChange={handleChange} />
                    </div>
                </form>
            </Modal>
        </div>
    );
};

// 9. Manajemen Hotel (DITAMBAH PAGINASI & SEARCH)
const HotelsComponent = () => {
    const defaultState = {
        name: '',
        address: '',
        city: 'Mekkah',
        country: 'Saudi Arabia',
        phone: '',
        email: '',
        rating: 5,
    };
    const {
        list, isLoading, error, successMessage, isModalOpen, isEditing,
        formState, pagination, searchTerm, setPage, setSearchTerm, // BARU
        handleChange, handleAddNew, handleEdit,
        handleCloseModal, handleSubmit, handleDelete
    } = useCRUD('hotels', defaultState);

    const columns = [
        { key: 'name', label: 'Nama Hotel' },
        { key: 'city', label: 'Kota' },
        { key: 'rating', label: 'Bintang', render: (item) => `${item.rating} Bintang` },
        { key: 'phone', label: 'Telepon' },
    ];

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Data Hotel</h2>
                <Button onClick={handleAddNew}>Tambah Data Hotel</Button>
            </div>
            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable columns={columns} data={list} onEdit={handleEdit} onDelete={handleDelete} />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}
            <Modal
                show={isModalOpen} onClose={handleCloseModal}
                title={isEditing ? "Edit Data Hotel" : "Tambah Data Hotel"}
                footer={<>
                    <Button onClick={handleCloseModal} variant="secondary" className="mr-2">Batal</Button>
                    <Button onClick={handleSubmit} type="submit" form="hotel-form">Simpan</Button>
                </>}
            >
                <form id="hotel-form" onSubmit={handleSubmit} className="space-y-4">
                    <FormInput label="Nama Hotel" name="name" value={formState.name} onChange={handleChange} required />
                    <FormTextarea label="Alamat" name="address" value={formState.address} onChange={handleChange} />
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormInput label="Kota" name="city" value={formState.city} onChange={handleChange} />
                        <FormInput label="Negara" name="country" value={formState.country} onChange={handleChange} />
                        <FormInput label="Bintang (1-5)" name="rating" type="number" min="1" max="5" value={formState.rating} onChange={handleChange} />
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Telepon" name="phone" value={formState.phone} onChange={handleChange} />
                        <FormInput label="Email" name="email" type="email" value={formState.email} onChange={handleChange} />
                    </div>
                </form>
            </Modal>
        </div>
    );
};

// 10. Manajemen Role (DITAMBAH PAGINASI & SEARCH)
const RolesComponent = () => {
    const defaultState = { role_key: '', role_name: '' };
    const {
        list, isLoading, error, successMessage, isModalOpen, isEditing,
        formState, pagination, searchTerm, setPage, setSearchTerm, // BARU
        handleChange, handleAddNew, handleEdit,
        handleCloseModal, handleSubmit, handleDelete
    } = useCRUD('roles', defaultState);

    const columns = [
        { key: 'role_name', label: 'Nama Role' },
        { key: 'role_key', label: 'Kunci Role (untuk sistem)' },
    ];

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Role Karyawan</h2>
                <Button onClick={handleAddNew}>Tambah Role Baru</Button>
            </div>
            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {/* BARU: Search Input */}
            <SearchInput initialValue={searchTerm} onSearch={setSearchTerm} />

            {isLoading ? <Spinner /> : (
                <>
                    <CrudTable columns={columns} data={list} onEdit={handleEdit} onDelete={handleDelete} />
                    {/* BARU: Pagination */}
                    <Pagination pagination={pagination} onPageChange={setPage} />
                </>
            )}
            <Modal
                show={isModalOpen} onClose={handleCloseModal}
                title={isEditing ? "Edit Role" : "Tambah Role Baru"}
                footer={<>
                    <Button onClick={handleCloseModal} variant="secondary" className="mr-2">Batal</Button>
                    <Button onClick={handleSubmit} type="submit" form="role-form">Simpan</Button>
                </>}
            >
                <form id="role-form" onSubmit={handleSubmit}>
                    <FormInput label="Nama Role" name="role_name" value={formState.role_name} onChange={handleChange} required placeholder="e.g., Staf Keuangan" />
                    <FormInput label="Kunci Role" name="role_key" value={formState.role_key} onChange={handleChange} required placeholder="e.g., finance_staff" />
                </form>
            </Modal>
        </div>
    );
};


// --- Komponen Utama Aplikasi (Navigasi & Router) ---

// Header
const AppHeader = ({ user, onLogout }) => (
    <header className="bg-blue-700 text-white shadow-lg">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex justify-between items-center h-16">
                <div className="flex-shrink-0">
                    <span className="text-2xl font-bold text-white">Travel</span>
                    <span className="text-2xl font-bold text-blue-200">Manajemen</span>
                </div>
                <div className="text-sm text-blue-100">
                    Selamat datang, <span className="font-medium text-white">{user.name || 'Pengguna'}</span>
                </div>
            </div>
        </div>
    </header>
);

// Navigasi Utama
const MainNav = ({ currentView, setView }) => {
    
    const navItems = [
        { key: 'dashboard', label: 'Dashboard' },
        { key: 'packages', label: 'Paket' },
        { key: 'categories', label: 'Kategori Paket' },
        { key: 'jamaah', label: 'Jemaah (Manifest)' },
        { key: 'finance', label: 'Keuangan' },
        { key: 'tasks', label: 'Tugas' },
        { key: 'data_master', label: 'Data Master', subItems: [
            { key: 'flights', label: 'Data Pesawat' },
            { key: 'hotels', label: 'Data Hotel' },
        ]},
        { key: 'hr', label: 'HRD', subItems: [
            { key: 'users', label: 'Staff' },
            { key: 'roles', label: 'Roles' },
        ]},
    ];

    return (
        <nav className="bg-white shadow-sm">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex space-x-6 overflow-x-auto py-1">
                    {navItems.map(item => {
                        if (item.subItems) {
                            return <NavDropdown key={item.key} item={item} currentView={currentView} setView={setView} />;
                        }
                        return <NavItem key={item.key} item={item} isActive={currentView === item.key} onClick={() => setView(item.key)} />;
                    })}
                </div>
            </div>
        </nav>
    );
};

// Item Navigasi
const NavItem = ({ item, isActive, onClick }) => (
    <button
        onClick={onClick}
        className={`px-3 py-4 text-sm font-medium border-b-2 transition-all duration-150 whitespace-nowrap
            ${isActive
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-800'
            }`}
    >
        {item.label}
    </button>
);

// Dropdown Navigasi
const NavDropdown = ({ item, currentView, setView }) => {
    const [isOpen, setIsOpen] = useState(false);
    const isActive = item.subItems.some(sub => sub.key === currentView);

    return (
        <div className="relative" onMouseLeave={() => setIsOpen(false)}>
            <button
                onMouseEnter={() => setIsOpen(true)}
                className={`px-3 py-4 text-sm font-medium border-b-2 transition-all duration-150 flex items-center whitespace-nowrap
                    ${isActive
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-800'
                    }`}
            >
                {item.label}
                <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            {isOpen && (
                <div className="absolute z-10 top-full left-0 mt-0 w-48 bg-white shadow-lg rounded-md border border-gray-200 py-1">
                    {item.subItems.map(subItem => (
                        <button
                            key={subItem.key}
                            onClick={() => {
                                setView(subItem.key);
                                setIsOpen(false);
                            }}
                            className={`block w-full text-left px-4 py-2 text-sm 
                                ${currentView === subItem.key ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-100'}`}
                        >
                            {subItem.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
};


// Komponen App Utama
const App = () => {
    const [currentView, setCurrentView] = useState('dashboard');
    const [user, setUser] = useState(wpData.user);

    const renderView = () => {
        switch (currentView) {
            case 'dashboard':
                return <DashboardComponent />;
            case 'packages':
                return <PackagesComponent />;
            case 'jamaah':
                return <JamaahComponent />;
            case 'finance':
                return <FinanceComponent />;
            case 'tasks':
                return <TasksComponent />;
            case 'users':
                return <UsersComponent />;
            case 'categories':
                return <CategoriesComponent />;
            case 'flights':
                return <FlightsComponent />;
            case 'hotels':
                return <HotelsComponent />;
            case 'roles':
                return <RolesComponent />;
            default:
                return <DashboardComponent />;
        }
    };

    // Auto-login untuk Super Admin
    useEffect(() => {
        const autoLoginAdmin = async () => {
            if (wpData.user && wpData.user.token) {
                try {
                    await api.get('users/me');
                    setUser(wpData.user);
                } catch (e) {
                    console.warn("Token kadaluarsa, mencoba auto-login...");
                    try {
                         const data = await api.post('auth/wp-login', {});
                         wpData.user.token = data.token;
                         setUser(data.user);
                    } catch (loginError) {
                         console.error("Auto-login gagal:", loginError);
                         setUser({ name: 'Error', role: 'guest', token: null });
                    }
                }
                return;
            }

            if (wpData.user && wpData.user.role === 'super_admin' && !wpData.user.token) {
                try {
                    console.log("Mencoba auto-login Super Admin...");
                    const data = await api.post('auth/wp-login', {});
                    wpData.user.token = data.token;
                    setUser(data.user);
                } catch (e) {
                    console.error("Gagal auto-login Super Admin:", e);
                }
            }
        };

        if (wpData.user.role === 'super_admin') {
            autoLoginAdmin();
        }
    }, []);


    return (
        <div className="min-h-screen bg-gray-100">
            <AppHeader user={user} />
            <MainNav currentView={currentView} setView={setCurrentView} />
            
            <main>
                <div className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 rounded-lg shadow-lg">
                        {renderView()}
                    </div>
                </div>
            </main>
        </div>
    );
};

// Render aplikasi React ke DOM
document.addEventListener('DOMContentLoaded', () => {
    const appRoot = document.getElementById('umh-react-app');
    if (appRoot) {
        ReactDOM.render(<App />, appRoot);
    } else {
        console.error("Target div 'umh-react-app' not found.");
    }
});