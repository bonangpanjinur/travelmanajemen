/**
 * File: src/index.jsx
 *
 * File frontend React utama. Dimodifikasi secara besar-besaran untuk:
 * 1. Menambahkan komponen baru: CategoriesComponent, FlightsComponent, HotelsComponent, RolesComponent.
 * 2. Memperbarui Navigasi (MainNav) untuk menampilkan menu baru.
 * 3. Memperbarui App router untuk menangani view baru.
 * 4. Memperbarui UsersComponent untuk menggunakan role dinamis.
 * 5. Memperbarui JamaahComponent untuk:
 * - Menambahkan fitur upload KTP & Paspor.
 * - Menghapus field payment_status lama.
 * - Menampilkan daftar pembayaran dinamis dari API.
 * - Menambahkan modal baru (PaymentModal) untuk mengelola pembayaran.
 */

import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { render } from '@wordpress/element';

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
    const baseStyle = "px-4 py-2 rounded-md font-medium transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2";
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

// --- API Client Sederhana (Tidak Berubah) ---

// Mengambil data dari global object `umh_wp_data`
const wpData = window.umh_wp_data || {
    api_url: '/wp-json/umh/v1/', // Fallback
    nonce: '',
    user: { token: '' }
};

const api = {
    // Ambil token dari data user
    getToken: () => wpData.user ? wpData.user.token : null,

    // Fungsi fetch utama
    request: async (endpoint, method = 'GET', body = null) => {
        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${api.getToken()}`
        };

        // Jika kita menggunakan WP-Nonce (opsional tapi bagus untuk keamanan)
        // headers['X-WP-Nonce'] = wpData.nonce;

        const config = {
            method: method,
            headers: headers,
        };

        if (body) {
            config.body = JSON.stringify(body);
        }

        try {
            const response = await fetch(wpData.api_url + endpoint, config);
            
            if (response.status === 204) { // No Content (untuk DELETE)
                return { success: true };
            }

            const data = await response.json();

            if (!response.ok) {
                // Tangani error dari API
                throw new Error(data.message || `Error ${response.status}: ${response.statusText}`);
            }

            return data;

        } catch (error) {
            console.error(`API Error (${method} ${endpoint}):`, error);
            throw error; // Lemparkan error agar bisa ditangkap oleh komponen
        }
    },

    get: (endpoint) => api.request(endpoint, 'GET'),
    post: (endpoint, body) => api.request(endpoint, 'POST', body),
    put: (endpoint, body) => api.request(endpoint, 'PUT', body),
    del: (endpoint) => api.request(endpoint, 'DELETE'),
};

// --- Hook Kustom untuk CRUD ---

/**
 * Hook kustom generik untuk operasi CRUD.
 * Mengelola state untuk list, loading, error, modal, dan form.
 * @param {string} apiName - Nama endpoint API (e.g., 'packages', 'jamaah')
 * @param {object} defaultFormState - Objek state awal untuk form
 * @param {function} [onDependenciesFetched] - (Opsional) Fungsi untuk fetch data lain (e.g., fetch paket untuk form jemaah)
 */
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

    // Fungsi untuk menampilkan pesan sukses sementara
    const showSuccess = (message) => {
        setSuccessMessage(message);
        setTimeout(() => setSuccessMessage(null), 3000);
    };

    // Fungsi untuk fetch dependencies (e.g., list paket, list user)
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

    // Fungsi untuk fetch data utama (list)
    const fetchData = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            await fetchDependencies(); // Selalu fetch dependencies dulu
            const data = await api.get(apiName);
            setList(data);
        } catch (err) {
            setError(`Gagal memuat data ${apiName}: ${err.message}`);
        } finally {
            setIsLoading(false);
        }
    }, [apiName, fetchDependencies]);

    // Fetch data saat komponen dimuat
    useEffect(() => {
        fetchData();
    }, [fetchData]);

    // Handler untuk perubahan input form
    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormState(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }));
    };

    // Membuka modal untuk item baru
    const handleAddNew = () => {
        setIsEditing(false);
        setCurrentItem(null);
        setFormState(defaultFormState);
        setModalOpen(true);
    };

    // Membuka modal untuk mengedit item
    const handleEdit = (item) => {
        setIsEditing(true);
        setCurrentItem(item);
        setFormState(item); // Isi form dengan data item
        setModalOpen(true);
    };

    // Menutup modal
    const handleCloseModal = () => {
        setModalOpen(false);
        setError(null); // Bersihkan error modal
    };

    // Menyimpan data (Create atau Update)
    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);
        try {
            let result;
            if (isEditing) {
                // Update
                result = await api.put(`${apiName}/${currentItem.id}`, formState);
                showSuccess("Data berhasil diperbarui.");
            } else {
                // Create
                result = await api.post(apiName, formState);
                showSuccess("Data berhasil ditambahkan.");
            }
            fetchData(); // Refresh list
            handleCloseModal();
        } catch (err) {
            setError(`Gagal menyimpan: ${err.message}`);
        }
    };

    // Menghapus item
    const handleDelete = async (id) => {
        if (!window.confirm("Apakah Anda yakin ingin menghapus data ini?")) {
            return;
        }
        setError(null);
        try {
            await api.del(`${apiName}/${id}`);
            showSuccess("Data berhasil dihapus.");
            fetchData(); // Refresh list
        } catch (err) {
            setError(`Gagal menghapus: ${err.message}`);
        }
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
        handleChange,
        handleAddNew,
        handleEdit,
        handleCloseModal,
        handleSubmit,
        handleDelete,
        fetchData, // expose fetchData untuk refresh manual
        setError, // expose setError untuk error kustom (spt upload)
        setFormState, // expose setFormState
        fetchDependencies, // expose fetchDependencies
    };
};

// --- Komponen Tabel Generik ---
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

// 1. Dashboard
const DashboardComponent = () => {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                setLoading(true);
                const data = await api.get('stats');
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
    if (!stats) return <p>Data statistik tidak ditemukan.</p>;

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard title="Total Jemaah" value={stats.total_jamaah || 0} />
            <StatCard title="Total Paket" value={stats.total_packages || 0} />
            <StatCard title="Total Pemasukan" value={formatCurrency(stats.total_income || 0)} />
            <StatCard title="Jemaah Lunas" value={stats.total_jamaah_lunas || 0} />
        </div>
    );
};

const StatCard = ({ title, value }) => (
    <div className="bg-white p-6 rounded-lg shadow-md">
        <h3 className="text-sm font-medium text-gray-500 uppercase">{title}</h3>
        <p className="mt-2 text-3xl font-semibold text-gray-900">{value}</p>
    </div>
);

// 2. Manajemen Paket
const PackagesComponent = () => {
    const defaultState = {
        name: '',
        category_id: '',
        description: '',
        price_quad: 0,
        price_triple: 0,
        price_double: 0,
        departure_date: '',
        duration_days: 0,
        status: 'draft',
    };

    const fetchPackageDependencies = useCallback(async () => {
        const categories = await api.get('categories');
        return { categories };
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
        handleChange,
        handleAddNew,
        handleEdit,
        handleCloseModal,
        handleSubmit,
        handleDelete
    } = useCRUD('packages', defaultState, fetchPackageDependencies);

    const columns = [
        { key: 'name', label: 'Nama Paket' },
        { 
            key: 'category', 
            label: 'Kategori',
            render: (item) => dependencies.categories?.find(c => c.id == item.category_id)?.name || 'N/A'
        },
        { 
            key: 'price_double', 
            label: 'Harga (Double)',
            render: (item) => formatCurrency(item.price_double)
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

            {isLoading ? <Spinner /> : (
                <CrudTable
                    columns={columns}
                    data={packages}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                />
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
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormInput label="Harga Double" name="price_double" type="number" value={formState.price_double} onChange={handleChange} />
                        <FormInput label="Harga Triple" name="price_triple" type="number" value={formState.price_triple} onChange={handleChange} />
                        <FormInput label="Harga Quad" name="price_quad" type="number" value={formState.price_quad} onChange={handleChange} />
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput label="Tanggal Keberangkatan" name="departure_date" type="date" value={formatDateForInput(formState.departure_date)} onChange={handleChange} required />
                        <FormInput label="Durasi (hari)" name="duration_days" type="number" value={formState.duration_days} onChange={handleChange} required />
                    </div>
                    <FormSelect label="Status" name="status" value={formState.status} onChange={handleChange} required>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="archived">Archived</option>
                    </FormSelect>
                </form>
            </Modal>
        </div>
    );
};

// 3. Manajemen Jemaah
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
        const packages = await api.get('packages');
        return { packages };
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
        handleChange,
        handleAddNew,
        handleEdit,
        handleCloseModal,
        handleSubmit,
        handleDelete,
        fetchData, // Ambil fetchData untuk refresh
        setError, // Ambil setError
        setFormState, // Ambil setFormState
    } = useCRUD('jamaah', defaultState, fetchJamaahDependencies);

    // === STATE BARU untuk Fitur Jemaah ===
    const [ktpFile, setKtpFile] = useState(null);
    const [passportFile, setPassportFile] = useState(null);
    
    // State untuk pembayaran
    const [payments, setPayments] = useState([]);
    const [isPaymentModalOpen, setPaymentModalOpen] = useState(false);
    const [currentPayment, setCurrentPayment] = useState(null); // null untuk baru, objek untuk edit
    const [isPaymentLoading, setPaymentLoading] = useState(false);
    const [paymentError, setPaymentError] = useState(null);

    // === FUNGSI BARU: Upload KTP/Paspor (sesuai Analisis) ===
    const handleFileUpload = async (file, uploadType, jamaahId) => {
        if (!file) return;
        
        // Buat FormData untuk kirim file
        const formData = new FormData();
        formData.append('file', file);
        formData.append('jamaah_id', jamaahId);
        formData.append('upload_type', uploadType); // 'ktp_scan' atau 'passport_scan'

        try {
            const token = api.getToken();
            const response = await fetch(`${wpData.api_url}uploads`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    // JANGAN set 'Content-Type', biarkan browser menanganinya
                },
                body: formData
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.message || 'Upload gagal');
            }

            const result = await response.json();
            
            // Update form state dengan URL file baru
            setFormState(prev => ({ ...prev, [uploadType]: result.file_url }));
            
            // Refresh data list utama untuk menampilkan URL baru di tabel (jika ada)
            fetchData();
            
            // Reset file input
            if (uploadType === 'ktp_scan') setKtpFile(null);
            if (uploadType === 'passport_scan') setPassportFile(null);

        } catch (err) {
            setError(`Upload ${uploadType} gagal: ${err.message}`);
        }
    };

    // === FUNGSI BARU: Fetch Pembayaran Saat Modal Edit Dibuka ===
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

    // Modifikasi handleEdit untuk memuat pembayaran
    const handleEditWithPayments = (item) => {
        handleEdit(item);
        fetchPayments(item.id); // Panggil fetch payments saat modal edit dibuka
    };

    // Menutup modal utama
    const handleCloseMainModal = () => {
        handleCloseModal();
        setPayments([]); // Kosongkan list pembayaran saat modal ditutup
        setPaymentError(null);
        setKtpFile(null);
        setPassportFile(null);
    };

    // === FUNGSI BARU: CRUD Pembayaran ===
    const handleOpenPaymentModal = (payment = null) => {
        setCurrentPayment(payment); // null = baru, object = edit
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
            const paymentPayload = {
                ...paymentData,
                jamaah_id: currentItem.id, // Pastikan jamaah_id ada
            };

            if (currentPayment) {
                // Update
                savedPayment = await api.put(`payments/${currentPayment.id}`, paymentPayload);
            } else {
                // Create
                savedPayment = await api.post('payments', paymentPayload);
            }

            // Jika ada file bukti, upload
            if (proofFile) {
                await handleProofUpload(savedPayment.id, proofFile);
            }
            
            // Refresh list pembayaran & data jemaah
            await fetchPayments(currentItem.id);
            await fetchData(); // Refresh data jemaah (untuk update amount_paid)
            
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
            // Refresh list pembayaran & data jemaah
            await fetchPayments(currentItem.id);
            await fetchData(); // Refresh data jemaah (untuk update amount_paid)
        } catch (err) {
            setPaymentError(`Gagal menghapus pembayaran: ${err.message}`);
        } finally {
            setPaymentLoading(false);
        }
    };


    // Definisi Kolom Tabel Jemaah
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

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Jemaah (Manifest)</h2>
                <Button onClick={handleAddNew}>Tambah Jemaah Baru</Button>
            </div>

            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {isLoading ? <Spinner /> : (
                <CrudTable
                    columns={columns}
                    data={jamaahList}
                    onEdit={handleEditWithPayments} // Gunakan fungsi baru
                    onDelete={handleDelete}
                />
            )}

            {/* Modal Utama: Tambah/Edit Jemaah */}
            <Modal
                show={isModalOpen}
                onClose={handleCloseMainModal} // Gunakan fungsi baru
                title={isEditing ? "Edit Jemaah" : "Tambah Jemaah Baru"}
                footer={
                    <>
                        <Button onClick={handleCloseMainModal} variant="secondary" className="mr-2">Batal</Button>
                        <Button onClick={handleSubmit} type="submit" form="jamaah-form">Simpan Jemaah</Button>
                    </>
                }
            >
                {/* Tampilkan error spesifik modal */}
                <Alert message={error} type="error" />

                <form id="jamaah-form" onSubmit={handleSubmit} className="space-y-4">
                    <FormInput label="Nama Lengkap" name="full_name" value={formState.full_name} onChange={handleChange} required />
                    <FormSelect label="Paket" name="package_id" value={formState.package_id} onChange={handleChange} required>
                        <option value="">Pilih Paket</option>
                        {dependencies.packages?.map(pkg => (
                            <option key={pkg.id} value={pkg.id}>{pkg.name} ({formatCurrency(pkg.price_double)})</option>
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

                    {/* --- BAGIAN FITUR BARU --- */}
                    {isEditing && (
                        <>
                            {/* 1. Upload Dokumen */}
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

                            {/* 2. Manajemen Pembayaran Dinamis */}
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

            {/* Modal Sekunder: Tambah/Edit Pembayaran */}
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

// --- Komponen BARU: Modal Pembayaran ---
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
            // Edit mode
            setForm({
                payment_date: formatDateForInput(payment.payment_date),
                amount: payment.amount,
                payment_stage: payment.payment_stage,
                status: payment.status,
                notes: payment.notes || '',
                proof_url: payment.proof_url || '',
            });
        } else {
            // Add mode
            setForm({
                payment_date: formatDateForInput(new Date()),
                amount: 0,
                payment_stage: 'DP 1',
                status: 'pending',
                notes: '',
                proof_url: '',
            });
        }
        setProofFile(null); // Selalu reset file
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


// 4. Manajemen Keuangan
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

    // Hitung Saldo
    const balance = useMemo(() => {
        return transactions.reduce((acc, trx) => {
            if (trx.type === 'income') return acc + parseFloat(trx.amount);
            if (trx.type === 'expense') return acc - parseFloat(trx.amount);
            return acc;
        }, 0);
    }, [transactions]);

    return (
        <div>
            <div className="flex justify-between items-center mb-6">
                <h2 className="text-2xl font-semibold text-gray-800">Manajemen Keuangan</h2>
                <div className="flex items-center space-x-4">
                    <div className="text-right">
                        <span className="text-sm text-gray-500">Total Saldo</span>
                        <p className={`text-xl font-semibold ${balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            {formatCurrency(balance)}
                        </p>
                    </div>
                    <Button onClick={handleAddNew}>Tambah Transaksi</Button>
                </div>
            </div>

            <Alert message={error} type="error" />
            <Alert message={successMessage} type="success" />

            {isLoading ? <Spinner /> : (
                <CrudTable
                    columns={columns}
                    data={transactions}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                />
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

// 5. Manajemen Tugas
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
        const [users, jamaah] = await Promise.all([
            api.get('users'),
            api.get('jamaah')
        ]);
        return { users, jamaah };
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

            {isLoading ? <Spinner /> : (
                <CrudTable
                    columns={columns}
                    data={tasks}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                />
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

// 6. Manajemen Pengguna (Staff)
const UsersComponent = () => {
    const defaultState = {
        wp_user_id: '',
        email: '',
        full_name: '',
        role: 'agent',
        phone: '',
        status: 'active',
        password: '', // Hanya untuk user baru
    };

    // --- MODIFIKASI: Fetch dependencies untuk ROLES ---
    const fetchUserDependencies = useCallback(async () => {
        const roles = await api.get('roles'); // Panggil API roles baru
        return { roles };
    }, []);

    const {
        list: userList,
        dependencies, // dependencies.roles akan tersedia di sini
        isLoading,
        error,
        successMessage,
        isModalOpen,
        isEditing,
        formState,
        handleChange,
        handleAddNew,
        handleEdit,
        handleCloseModal,
        handleSubmit,
        handleDelete
    } = useCRUD('users', defaultState, fetchUserDependencies); // Tambahkan fetchUserDependencies

    const columns = [
        { key: 'full_name', label: 'Nama Lengkap' },
        { key: 'email', label: 'Email (WP Login)' },
        { 
            key: 'role', 
            label: 'Role',
            // --- MODIFIKASI: Tampilkan role_name dari dependencies ---
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

            {isLoading ? <Spinner /> : (
                <CrudTable
                    columns={columns}
                    data={userList}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                />
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
                        label="Email (Login WP)" 
                        name="email" 
                        type="email" 
                        value={formState.email} 
                        onChange={handleChange} 
                        required 
                        disabled={isEditing} // Email tidak bisa diubah
                    />
                    {!isEditing && (
                        <FormInput label="Password" name="password" type="password" value={formState.password} onChange={handleChange} required />
                    )}
                    <FormInput label="Telepon" name="phone" value={formState.phone} onChange={handleChange} />
                    
                    {/* --- MODIFIKASI: Gunakan Select Dinamis untuk Role --- */}
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

// --- 4 KOMPONEN BARU (Kategori, Pesawat, Hotel, Roles) ---

// 7. Manajemen Kategori (BARU)
const CategoriesComponent = () => {
    const defaultState = { name: '', description: '' };
    const {
        list, isLoading, error, successMessage, isModalOpen, isEditing,
        formState, handleChange, handleAddNew, handleEdit,
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
            {isLoading ? <Spinner /> : (
                <CrudTable columns={columns} data={list} onEdit={handleEdit} onDelete={handleDelete} />
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

// 8. Manajemen Pesawat (BARU)
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
        formState, handleChange, handleAddNew, handleEdit,
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
            {isLoading ? <Spinner /> : (
                <CrudTable columns={columns} data={list} onEdit={handleEdit} onDelete={handleDelete} />
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

// 9. Manajemen Hotel (BARU)
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
        formState, handleChange, handleAddNew, handleEdit,
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
            {isLoading ? <Spinner /> : (
                <CrudTable columns={columns} data={list} onEdit={handleEdit} onDelete={handleDelete} />
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

// 10. Manajemen Role (BARU)
const RolesComponent = () => {
    const defaultState = { role_key: '', role_name: '' };
    const {
        list, isLoading, error, successMessage, isModalOpen, isEditing,
        formState, handleChange, handleAddNew, handleEdit,
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
            {isLoading ? <Spinner /> : (
                <CrudTable columns={columns} data={list} onEdit={handleEdit} onDelete={handleDelete} />
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
    <header className="bg-white shadow-md">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex justify-between items-center h-16">
                <div className="flex-shrink-0">
                    <span className="text-2xl font-bold text-blue-600">Travel</span>
                    <span className="text-2xl font-bold text-gray-700">Manajemen</span>
                </div>
                <div className="text-sm text-gray-600">
                    Selamat datang, <span className="font-medium text-gray-800">{user.name || 'Pengguna'}</span>
                    {/* <button onClick={onLogout} className="ml-4 text-blue-600 hover:underline">Logout</button> */}
                </div>
            </div>
        </div>
    </header>
);

// Navigasi Utama
const MainNav = ({ currentView, setView }) => {
    
    // --- MODIFIKASI: Tambahkan menu baru ---
    const navItems = [
        { key: 'dashboard', label: 'Dashboard' },
        { key: 'packages', label: 'Paket' },
        { key: 'categories', label: 'Kategori Paket' }, // BARU
        { key: 'jamaah', label: 'Jemaah (Manifest)' },
        { key: 'finance', label: 'Keuangan' },
        { key: 'tasks', label: 'Tugas' },
        { key: 'data_master', label: 'Data Master', subItems: [ // Grup BARU
            { key: 'flights', label: 'Data Pesawat' },
            { key: 'hotels', label: 'Data Hotel' },
        ]},
        { key: 'hr', label: 'HRD', subItems: [ // Grup BARU
            { key: 'users', label: 'Staff' },
            { key: 'roles', label: 'Roles' },
        ]},
    ];

    return (
        <nav className="bg-white shadow-sm">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex space-x-6">
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
        className={`px-3 py-4 text-sm font-medium border-b-2 transition-all duration-150
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
                className={`px-3 py-4 text-sm font-medium border-b-2 transition-all duration-150 flex items-center
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

    // --- MODIFIKASI: Router untuk render komponen ---
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
            // --- Rute BARU ---
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
        render(<App />, appRoot);
    }
});