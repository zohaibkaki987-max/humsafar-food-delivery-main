// Humsafar Admin Panel JavaScript

// Sample Data for Admin Panel
const adminData = {
    recentOrders: [
        {
            id: 'ORD001',
            customer: 'John Doe',
            restaurant: 'Pizza Palace',
            amount: '$24.99',
            status: 'delivered',
            time: '2023-12-15 18:30'
        },
        {
            id: 'ORD002',
            customer: 'Sarah Wilson',
            restaurant: 'Burger Hub',
            amount: '$18.50',
            status: 'preparing',
            time: '2023-12-15 18:15'
        },
        {
            id: 'ORD003',
            customer: 'Mike Johnson',
            restaurant: 'Sushi Express',
            amount: '$32.75',
            status: 'confirmed',
            time: '2023-12-15 17:45'
        },
        {
            id: 'ORD004',
            customer: 'Emily Davis',
            restaurant: 'Pasta Paradise',
            amount: '$28.99',
            status: 'pending',
            time: '2023-12-15 17:30'
        },
        {
            id: 'ORD005',
            customer: 'David Brown',
            restaurant: 'Green Garden',
            amount: '$22.50',
            status: 'ready',
            time: '2023-12-15 17:15'
        }
    ],
    
    restaurants: [
        {
            id: 1,
            name: 'Pizza Palace',
            cuisine: 'Italian',
            rating: 4.8,
            orders: 245,
            status: 'active',
            joined: '2023-01-15'
        },
        {
            id: 2,
            name: 'Burger Hub',
            cuisine: 'American',
            rating: 4.6,
            orders: 198,
            status: 'active',
            joined: '2023-02-20'
        },
        {
            id: 3,
            name: 'Sushi Express',
            cuisine: 'Japanese',
            rating: 4.7,
            orders: 176,
            status: 'active',
            joined: '2023-01-28'
        },
        {
            id: 4,
            name: 'Pasta Paradise',
            cuisine: 'Italian',
            rating: 4.5,
            orders: 154,
            status: 'pending',
            joined: '2023-03-10'
        },
        {
            id: 5,
            name: 'Green Garden',
            cuisine: 'Healthy',
            rating: 4.4,
            orders: 132,
            status: 'active',
            joined: '2023-02-15'
        }
    ],
    
    riders: [
        {
            id: 1,
            name: 'Mike Johnson',
            vehicle: 'Motorcycle',
            phone: '+1 234 567 8901',
            rating: 4.9,
            deliveries: 89,
            earnings: '$1,245',
            status: 'active'
        },
        {
            id: 2,
            name: 'Sarah Wilson',
            vehicle: 'Scooter',
            phone: '+1 234 567 8902',
            rating: 4.8,
            deliveries: 76,
            earnings: '$1,120',
            status: 'active'
        },
        {
            id: 3,
            name: 'David Brown',
            vehicle: 'Bicycle',
            phone: '+1 234 567 8903',
            rating: 4.7,
            deliveries: 72,
            earnings: '$980',
            status: 'offline'
        },
        {
            id: 4,
            name: 'Lisa Garcia',
            vehicle: 'Car',
            phone: '+1 234 567 8904',
            rating: 4.6,
            deliveries: 65,
            earnings: '$1,050',
            status: 'active'
        }
    ],
    
    users: [
        {
            id: 1,
            name: 'John Doe',
            email: 'johndoe@email.com',
            phone: '+1 234 567 8900',
            orders: 24,
            totalSpent: '$458.75',
            status: 'active',
            joined: '2023-01-15'
        },
        {
            id: 2,
            name: 'Sarah Wilson',
            email: 'sarahw@email.com',
            phone: '+1 234 567 8901',
            orders: 18,
            totalSpent: '$342.50',
            status: 'active',
            joined: '2023-02-20'
        },
        {
            id: 3,
            name: 'Mike Johnson',
            email: 'mikej@email.com',
            phone: '+1 234 567 8902',
            orders: 32,
            totalSpent: '$678.90',
            status: 'active',
            joined: '2023-01-10'
        },
        {
            id: 4,
            name: 'Emily Davis',
            email: 'emilyd@email.com',
            phone: '+1 234 567 8903',
            orders: 12,
            totalSpent: '$234.75',
            status: 'inactive',
            joined: '2023-03-05'
        }
    ]
};

// Initialize Admin Panel
document.addEventListener('DOMContentLoaded', function() {
    initializeAdminPanel();
    setupAdminEventListeners();
    loadCurrentDate();
});

// Initialize Admin Panel Components
function initializeAdminPanel() {
    // Load recent orders on dashboard
    if (document.getElementById('recent-orders-table')) {
        loadRecentOrders();
    }
    
    // Load orders table
    if (document.getElementById('orders-table')) {
        loadOrdersTable();
    }
    
    // Load restaurants table
    if (document.getElementById('restaurants-table')) {
        loadRestaurantsTable();
    }
    
    // Load riders table
    if (document.getElementById('riders-table')) {
        loadRidersTable();
    }
    
    // Load users table
    if (document.getElementById('users-table')) {
        loadUsersTable();
    }
    
    // Initialize settings tabs
    initializeSettingsTabs();
    
    // Initialize modals
    initializeAdminModals();
}

// Setup Event Listeners
function setupAdminEventListeners() {
    // Add restaurant button
    const addRestaurantBtn = document.getElementById('add-restaurant-btn');
    if (addRestaurantBtn) {
        addRestaurantBtn.addEventListener('click', function() {
            openModal('restaurant-modal');
        });
    }
    
    // Add rider button
    const addRiderBtn = document.getElementById('add-rider-btn');
    if (addRiderBtn) {
        addRiderBtn.addEventListener('click', function() {
            openModal('rider-modal');
        });
    }
    
    // Restaurant form
    const restaurantForm = document.getElementById('restaurant-form');
    if (restaurantForm) {
        restaurantForm.addEventListener('submit', handleRestaurantSubmit);
    }
    
    // Rider form
    const riderForm = document.getElementById('rider-form');
    if (riderForm) {
        riderForm.addEventListener('submit', handleRiderSubmit);
    }
    
    // Search functionality
    const searchInputs = document.querySelectorAll('#order-search, #restaurant-search, #rider-search, #user-search');
    searchInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', debounce(handleSearch, 300));
        }
    });
    
    // Filter functionality
    const filters = document.querySelectorAll('#status-filter, #cuisine-filter, #vehicle-filter, #sort-users');
    filters.forEach(filter => {
        if (filter) {
            filter.addEventListener('change', handleFilter);
        }
    });
}

// Load Current Date
function loadCurrentDate() {
    const dateElement = document.getElementById('current-date');
    if (dateElement) {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        dateElement.textContent = now.toLocaleDateString('en-US', options);
    }
}

// Load Recent Orders
function loadRecentOrders() {
    const tableBody = document.getElementById('recent-orders-table');
    if (!tableBody) return;
    
    tableBody.innerHTML = adminData.recentOrders.map(order => `
        <tr>
            <td>${order.id}</td>
            <td>${order.customer}</td>
            <td>${order.restaurant}</td>
            <td>${order.amount}</td>
            <td><span class="status-badge status-${order.status}">${order.status}</span></td>
            <td>
                <button class="btn-action btn-view" onclick="viewOrder('${order.id}')">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Load Orders Table
function loadOrdersTable() {
    const tableBody = document.getElementById('orders-table');
    if (!tableBody) return;
    
    tableBody.innerHTML = adminData.recentOrders.map(order => `
        <tr>
            <td>${order.id}</td>
            <td>${order.customer}</td>
            <td>${order.restaurant}</td>
            <td>2 items</td>
            <td>${order.amount}</td>
            <td><span class="status-badge status-${order.status}">${order.status}</span></td>
            <td>${order.time}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-action btn-view" onclick="viewOrder('${order.id}')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action btn-edit" onclick="editOrder('${order.id}')">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Load Restaurants Table
function loadRestaurantsTable() {
    const tableBody = document.getElementById('restaurants-table');
    if (!tableBody) return;
    
    tableBody.innerHTML = adminData.restaurants.map(restaurant => `
        <tr>
            <td><input type="checkbox" class="row-select"></td>
            <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-store"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500;">${restaurant.name}</div>
                        <div style="font-size: 12px; color: #666;">${restaurant.orders} orders</div>
                    </div>
                </div>
            </td>
            <td>${restaurant.cuisine}</td>
            <td>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-star" style="color: #ffc107;"></i>
                    ${restaurant.rating}
                </div>
            </td>
            <td>${restaurant.orders}</td>
            <td><span class="status-badge status-${restaurant.status}">${restaurant.status}</span></td>
            <td>${restaurant.joined}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-action btn-view" onclick="viewRestaurant(${restaurant.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action btn-edit" onclick="editRestaurant(${restaurant.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-action btn-delete" onclick="deleteRestaurant(${restaurant.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Load Riders Table
function loadRidersTable() {
    const tableBody = document.getElementById('riders-table');
    if (!tableBody) return;
    
    tableBody.innerHTML = adminData.riders.map(rider => `
        <tr>
            <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500;">${rider.name}</div>
                        <div style="font-size: 12px; color: #666;">${rider.vehicle}</div>
                    </div>
                </div>
            </td>
            <td>${rider.vehicle}</td>
            <td>${rider.phone}</td>
            <td>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-star" style="color: #ffc107;"></i>
                    ${rider.rating}
                </div>
            </td>
            <td>${rider.deliveries}</td>
            <td>${rider.earnings}</td>
            <td><span class="status-badge status-${rider.status}">${rider.status}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="btn-action btn-view" onclick="viewRider(${rider.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action btn-edit" onclick="editRider(${rider.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Load Users Table
function loadUsersTable() {
    const tableBody = document.getElementById('users-table');
    if (!tableBody) return;
    
    tableBody.innerHTML = adminData.users.map(user => `
        <tr>
            <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div>
                        <div style="font-weight: 500;">${user.name}</div>
                        <div style="font-size: 12px; color: #666;">${user.orders} orders</div>
                    </div>
                </div>
            </td>
            <td>${user.email}</td>
            <td>${user.phone}</td>
            <td>${user.orders}</td>
            <td>${user.totalSpent}</td>
            <td><span class="status-badge status-${user.status}">${user.status}</span></td>
            <td>${user.joined}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-action btn-view" onclick="viewUser(${user.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-action btn-edit" onclick="editUser(${user.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Initialize Settings Tabs
function initializeSettingsTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to current button and content
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
}

// Initialize Admin Modals
function initializeAdminModals() {
    // Close modals when clicking X
    document.querySelectorAll('.close-modal').forEach(closeBtn => {
        closeBtn.addEventListener('click', closeAllModals);
    });
    
    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeAllModals();
            }
        });
    });
}

// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });
    document.body.style.overflow = 'auto';
}

// Form Handlers
function handleRestaurantSubmit(e) {
    e.preventDefault();
    alert('Restaurant added successfully!');
    closeAllModals();
    e.target.reset();
}

function handleRiderSubmit(e) {
    e.preventDefault();
    alert('Rider added successfully!');
    closeAllModals();
    e.target.reset();
}

// View Functions (for demo purposes)
function viewOrder(orderId) {
    openModal('order-modal');
    // In real app, you would load order details based on orderId
}

function editOrder(orderId) {
    alert(`Editing order: ${orderId}`);
}

function viewRestaurant(restaurantId) {
    alert(`Viewing restaurant: ${restaurantId}`);
}

function editRestaurant(restaurantId) {
    alert(`Editing restaurant: ${restaurantId}`);
}

function deleteRestaurant(restaurantId) {
    if (confirm('Are you sure you want to delete this restaurant?')) {
        alert(`Restaurant ${restaurantId} deleted successfully!`);
    }
}

function viewRider(riderId) {
    alert(`Viewing rider: ${riderId}`);
}

function editRider(riderId) {
    alert(`Editing rider: ${riderId}`);
}

function viewUser(userId) {
    const user = adminData.users.find(u => u.id === userId);
    if (user) {
        document.getElementById('modal-user-name').textContent = user.name;
        document.getElementById('modal-user-email').textContent = user.email;
        document.getElementById('total-orders').textContent = user.orders;
        document.getElementById('total-spent').textContent = user.totalSpent;
        document.getElementById('joined-date').textContent = user.joined;
        openModal('user-modal');
    }
}

function editUser(userId) {
    alert(`Editing user: ${userId}`);
}

// Search and Filter Functions
function handleSearch(e) {
    const searchTerm = e.target.value.toLowerCase();
    const tableType = e.target.id.replace('-search', '');
    console.log(`Searching ${tableType} for:`, searchTerm);
    // Implement search logic based on tableType
}

function handleFilter(e) {
    const filterType = e.target.id;
    const filterValue = e.target.value;
    console.log(`Filtering by ${filterType}:`, filterValue);
    // Implement filter logic
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Settings Functions
function copyApiKey() {
    const apiKeyInput = document.getElementById('api-key');
    apiKeyInput.select();
    document.execCommand('copy');
    alert('API Key copied to clipboard!');
}

function generateApiKey() {
    if (confirm('Are you sure you want to generate a new API key? The old key will be invalidated.')) {
        const newKey = 'sk_live_' + Math.random().toString(36).substr(2, 32);
        document.getElementById('api-key').value = newKey;
        alert('New API key generated successfully!');
    }
}

// Export functions for global access
window.viewOrder = viewOrder;
window.editOrder = editOrder;
window.viewRestaurant = viewRestaurant;
window.editRestaurant = editRestaurant;
window.deleteRestaurant = deleteRestaurant;
window.viewRider = viewRider;
window.editRider = editRider;
window.viewUser = viewUser;
window.editUser = editUser;
window.copyApiKey = copyApiKey;
window.generateApiKey = generateApiKey;