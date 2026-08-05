// Humsafar Food Delivery - Main JavaScript File

// Sample Data
const categories = [
    { id: 1, name: 'Pizza', icon: 'fas fa-pizza-slice' },
    { id: 2, name: 'Burger', icon: 'fas fa-hamburger' },
    { id: 3, name: 'Sushi', icon: 'fas fa-fish' },
    { id: 4, name: 'Pasta', icon: 'fas fa-utensils' },
    { id: 5, name: 'Salad', icon: 'fas fa-leaf' },
    { id: 6, name: 'Dessert', icon: 'fas fa-ice-cream' },
    { id: 7, name: 'Chinese', icon: 'fas fa-utensil-spoon' },
    { id: 8, name: 'Indian', icon: 'fas fa-pepper-hot' }
];

const restaurants = [
    {
        id: 1,
        name: 'Pizza Palace',
        cuisine: 'Italian • Pizza',
        rating: 4.5,
        deliveryTime: '25-35 min',
        price: '$$',
        image: 'pizza'
    },
    {
        id: 2,
        name: 'Burger Hub',
        cuisine: 'American • Burgers',
        rating: 4.3,
        deliveryTime: '20-30 min',
        price: '$',
        image: 'burger'
    },
    {
        id: 3,
        name: 'Sushi Express',
        cuisine: 'Japanese • Sushi',
        rating: 4.7,
        deliveryTime: '30-40 min',
        price: '$$$',
        image: 'sushi'
    },
    {
        id: 4,
        name: 'Pasta Paradise',
        cuisine: 'Italian • Pasta',
        rating: 4.4,
        deliveryTime: '25-35 min',
        price: '$$',
        image: 'pasta'
    },
    {
        id: 5,
        name: 'Green Garden',
        cuisine: 'Healthy • Salads',
        rating: 4.2,
        deliveryTime: '15-25 min',
        price: '$$',
        image: 'salad'
    },
    {
        id: 6,
        name: 'Sweet Dreams',
        cuisine: 'Desserts • Bakery',
        rating: 4.6,
        deliveryTime: '20-30 min',
        price: '$',
        image: 'dessert'
    }
];

// Cart Management
let cart = JSON.parse(localStorage.getItem('humsafar_cart')) || [];
let currentUser = JSON.parse(localStorage.getItem('humsafar_user')) || null;

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    updateCartCount();
});

// Initialize App
function initializeApp() {
    // Load categories on homepage
    if (document.getElementById('categories-container')) {
        loadCategories();
    }
    
    // Load restaurants on homepage
    if (document.getElementById('restaurants-container')) {
        loadRestaurants();
    }
    
    // Load restaurants grid on restaurants page
    if (document.getElementById('restaurants-grid')) {
        loadRestaurantsGrid();
    }
    
    // Initialize authentication forms
    if (document.getElementById('login-form')) {
        initializeLoginForm();
    }
    
    if (document.getElementById('signup-form')) {
        initializeSignupForm();
    }
    
    // Initialize account functionality
    if (document.getElementById('profile-form')) {
        initializeProfileForm();
    }
    
    // Initialize cart functionality
    if (document.querySelector('.cart-container')) {
        initializeCart();
    }
    
    // Initialize modals
    initializeModals();
    
    // Check authentication status
    checkAuthStatus();
}

// Setup Event Listeners
function setupEventListeners() {
    // Search functionality
    const searchInput = document.querySelector('.search-bar input');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }
    
    // Find Food button
    const findFoodBtn = document.getElementById('find-food');
    if (findFoodBtn) {
        findFoodBtn.addEventListener('click', handleFindFood);
    }
    
    // Cart button
    const cartBtn = document.getElementById('cart-btn');
    if (cartBtn) {
        cartBtn.addEventListener('click', function(e) {
            if (!currentUser) {
                e.preventDefault();
                alert('Please sign in to view your cart');
                window.location.href = 'login.html';
            }
        });
    }
    
    // Restaurant owner and rider application forms
    const restaurantForm = document.getElementById('restaurant-registration-form');
    if (restaurantForm) {
        restaurantForm.addEventListener('submit', handleRestaurantRegistration);
    }
    
    const riderForm = document.getElementById('rider-application-form');
    if (riderForm) {
        riderForm.addEventListener('submit', handleRiderApplication);
    }
}

// Load Categories
function loadCategories() {
    const container = document.getElementById('categories-container');
    if (!container) return;
    
    container.innerHTML = categories.map(category => `
        <div class="category-card" onclick="handleCategoryClick(${category.id})">
            <i class="${category.icon}"></i>
            <h3>${category.name}</h3>
        </div>
    `).join('');
}

// Load Restaurants
function loadRestaurants() {
    const container = document.getElementById('restaurants-container');
    if (!container) return;
    
    container.innerHTML = restaurants.map(restaurant => `
        <div class="restaurant-card" onclick="handleRestaurantClick(${restaurant.id})">
            <div class="restaurant-img" style="background: linear-gradient(45deg, #f0f0f0, #e0e0e0);"></div>
            <div class="restaurant-info">
                <h3>${restaurant.name}</h3>
                <p>${restaurant.cuisine}</p>
                <div class="restaurant-meta">
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        ${restaurant.rating}
                    </div>
                    <span class="delivery-time">${restaurant.deliveryTime}</span>
                    <span class="price">${restaurant.price}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// Load Restaurants Grid
function loadRestaurantsGrid() {
    const container = document.getElementById('restaurants-grid');
    if (!container) return;
    
    container.innerHTML = restaurants.map(restaurant => `
        <div class="restaurant-card" onclick="handleRestaurantClick(${restaurant.id})">
            <div class="restaurant-img" style="background: linear-gradient(45deg, #f0f0f0, #e0e0e0);"></div>
            <div class="restaurant-info">
                <h3>${restaurant.name}</h3>
                <p>${restaurant.cuisine}</p>
                <div class="restaurant-meta">
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        ${restaurant.rating}
                    </div>
                    <span class="delivery-time">${restaurant.deliveryTime}</span>
                    <span class="price">${restaurant.price}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// Handle Category Click
function handleCategoryClick(categoryId) {
    const category = categories.find(cat => cat.id === categoryId);
    if (category) {
        window.location.href = `restaurants.html?category=${category.name.toLowerCase()}`;
    }
}

// Handle Restaurant Click
function handleRestaurantClick(restaurantId) {
    // For demo purposes, redirect to cart page
    // In a real app, this would go to restaurant detail page
    window.location.href = 'my-cart.html';
}

// Search Functionality
function handleSearch(e) {
    const searchTerm = e.target.value.toLowerCase();
    console.log('Searching for:', searchTerm);
    // Implement search logic here
}

function handleFindFood() {
    const addressInput = document.getElementById('delivery-address');
    if (addressInput && addressInput.value.trim()) {
        window.location.href = 'restaurants.html';
    } else {
        alert('Please enter your delivery address');
        addressInput.focus();
    }
}

// Cart Management Functions
function addToCart(item) {
    const existingItem = cart.find(cartItem => cartItem.id === item.id);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({ ...item, quantity: 1 });
    }
    
    updateCartStorage();
    updateCartCount();
}

function removeFromCart(itemId) {
    cart = cart.filter(item => item.id !== itemId);
    updateCartStorage();
    updateCartCount();
    if (document.querySelector('.cart-container')) {
        initializeCart();
    }
}

function updateQuantity(itemId, change) {
    const item = cart.find(item => item.id === itemId);
    if (item) {
        item.quantity += change;
        if (item.quantity <= 0) {
            removeFromCart(itemId);
        } else {
            updateCartStorage();
            updateCartCount();
            if (document.querySelector('.cart-container')) {
                initializeCart();
            }
        }
    }
}

function updateCartStorage() {
    localStorage.setItem('humsafar_cart', JSON.stringify(cart));
}

function updateCartCount() {
    const cartCountElements = document.querySelectorAll('.cart-count');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    cartCountElements.forEach(element => {
        element.textContent = totalItems;
        element.style.display = totalItems > 0 ? 'inline-flex' : 'none';
    });
}

function getCartTotal() {
    return cart.reduce((total, item) => total + (item.price * item.quantity), 0);
}

// Initialize Cart
function initializeCart() {
    const cartItemsList = document.querySelector('.cart-items-list');
    const orderSummary = document.querySelector('.order-summary .summary-card');
    
    if (cartItemsList) {
        // For demo, add some sample items if cart is empty
        if (cart.length === 0) {
            cart = [
                { id: 1, name: 'Margherita Pizza', price: 14.99, quantity: 1 },
                { id: 2, name: 'Garlic Bread', price: 5.99, quantity: 1 }
            ];
            updateCartStorage();
        }
        
        cartItemsList.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div class="item-image">
                    <i class="fas fa-${getItemIcon(item.name)}"></i>
                </div>
                <div class="item-details">
                    <h4>${item.name}</h4>
                    <p>${getItemDescription(item.name)}</p>
                    <span class="item-price">$${item.price.toFixed(2)}</span>
                </div>
                <div class="item-quantity">
                    <button class="quantity-btn minus" onclick="updateQuantity(${item.id}, -1)">-</button>
                    <span class="quantity">${item.quantity}</span>
                    <button class="quantity-btn plus" onclick="updateQuantity(${item.id}, 1)">+</button>
                </div>
                <div class="item-total">$${(item.price * item.quantity).toFixed(2)}</div>
                <button class="item-remove" onclick="removeFromCart(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `).join('');
    }
    
    if (orderSummary) {
        const subtotal = getCartTotal();
        const serviceFee = 1.50;
        const tax = subtotal * 0.1; // 10% tax
        const total = subtotal + serviceFee + tax;
        
        document.querySelector('.summary-row:nth-child(1) span:last-child').textContent = `$${subtotal.toFixed(2)}`;
        document.querySelector('.summary-row:nth-child(3) span:last-child').textContent = `$${serviceFee.toFixed(2)}`;
        document.querySelector('.summary-row:nth-child(4) span:last-child').textContent = `$${tax.toFixed(2)}`;
        document.querySelector('.summary-row.total span:last-child').textContent = `$${total.toFixed(2)}`;
        
        // Update checkout button
        const checkoutBtn = document.querySelector('.btn-checkout');
        if (checkoutBtn) {
            checkoutBtn.innerHTML = `<i class="fas fa-lock"></i> Proceed to Checkout - $${total.toFixed(2)}`;
        }
    }
    
    // Quantity button event listeners
    document.querySelectorAll('.quantity-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Handled by onclick attributes for simplicity
        });
    });
}

function getItemIcon(itemName) {
    const icons = {
        'Pizza': 'pizza-slice',
        'Burger': 'hamburger',
        'Bread': 'bread-slice',
        'Pasta': 'utensils',
        'Salad': 'leaf',
        'Default': 'utensils'
    };
    
    for (const [key, icon] of Object.entries(icons)) {
        if (itemName.toLowerCase().includes(key.toLowerCase())) {
            return icon;
        }
    }
    return icons.Default;
}

function getItemDescription(itemName) {
    const descriptions = {
        'Margherita Pizza': 'Classic pizza with tomato sauce and mozzarella',
        'Garlic Bread': 'Freshly baked bread with garlic butter',
        'Default': 'Delicious food item'
    };
    
    return descriptions[itemName] || descriptions.Default;
}

// Authentication Functions
function initializeLoginForm() {
    const loginForm = document.getElementById('login-form');
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        if (validateLogin(email, password)) {
            // Simulate login success
            currentUser = {
                id: 1,
                firstName: 'John',
                lastName: 'Doe',
                email: email,
                phone: '+1 234 567 8900'
            };
            
            localStorage.setItem('humsafar_user', JSON.stringify(currentUser));
            alert('Login successful!');
            window.location.href = 'index.html';
        } else {
            alert('Invalid email or password. Please try again.');
        }
    });
}

function initializeSignupForm() {
    const signupForm = document.getElementById('signup-form');
    signupForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const firstName = document.getElementById('first-name').value;
        const lastName = document.getElementById('last-name').value;
        const email = document.getElementById('email').value;
        const phone = document.getElementById('phone').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        
        if (validateSignup(firstName, lastName, email, phone, password, confirmPassword)) {
            // Simulate signup success
            currentUser = {
                id: Date.now(),
                firstName: firstName,
                lastName: lastName,
                email: email,
                phone: phone
            };
            
            localStorage.setItem('humsafar_user', JSON.stringify(currentUser));
            alert('Account created successfully!');
            window.location.href = 'index.html';
        }
    });
}

function validateLogin(email, password) {
    // Basic validation - in real app, this would check against a database
    return email && password && email.includes('@') && password.length >= 6;
}

function validateSignup(firstName, lastName, email, phone, password, confirmPassword) {
    if (!firstName || !lastName || !email || !phone || !password) {
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (!email.includes('@')) {
        alert('Please enter a valid email address.');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters long.');
        return false;
    }
    
    if (password !== confirmPassword) {
        alert('Passwords do not match.');
        return false;
    }
    
    if (!document.querySelector('input[name="terms"]').checked) {
        alert('Please agree to the Terms of Service and Privacy Policy.');
        return false;
    }
    
    return true;
}

function checkAuthStatus() {
    if (currentUser) {
        // Update UI for logged-in user
        const signInLinks = document.querySelectorAll('.sign-in');
        signInLinks.forEach(link => {
            link.textContent = 'Logout';
            link.href = 'javascript:void(0)';
            link.onclick = handleLogout;
        });
        
        const signUpLinks = document.querySelectorAll('.sign-up');
        signUpLinks.forEach(link => {
            link.style.display = 'none';
        });
    }
}

function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        currentUser = null;
        localStorage.removeItem('humsafar_user');
        window.location.href = 'index.html';
    }
}

// Profile Management
function initializeProfileForm() {
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Profile updated successfully!');
        });
    }
    
    // Change email modal
    const changeEmailBtn = document.getElementById('change-email');
    if (changeEmailBtn) {
        changeEmailBtn.addEventListener('click', function() {
            openModal('email-modal');
        });
    }
    
    // Change phone modal
    const changePhoneBtn = document.getElementById('change-phone');
    if (changePhoneBtn) {
        changePhoneBtn.addEventListener('click', function() {
            openModal('phone-modal');
        });
    }
    
    // Address management
    const addAddressBtn = document.getElementById('add-address-btn');
    if (addAddressBtn) {
        addAddressBtn.addEventListener('click', function() {
            openAddressModal();
        });
    }
    
    // Address action buttons
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const addressId = this.getAttribute('data-address');
            editAddress(addressId);
        });
    });
    
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const addressId = this.getAttribute('data-address');
            deleteAddress(addressId);
        });
    });
    
    document.querySelectorAll('.btn-set-default').forEach(btn => {
        btn.addEventListener('click', function() {
            const addressId = this.getAttribute('data-address');
            setDefaultAddress(addressId);
        });
    });
}

// Modal Management
function initializeModals() {
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
    
    // Email form
    const emailForm = document.getElementById('email-form');
    if (emailForm) {
        emailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Email updated successfully!');
            closeAllModals();
        });
    }
    
    // Phone form
    const phoneForm = document.getElementById('phone-form');
    if (phoneForm) {
        phoneForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Phone number updated successfully!');
            closeAllModals();
        });
    }
    
    // Address form
    const addressForm = document.getElementById('address-form');
    if (addressForm) {
        addressForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveAddress();
        });
    }
    
    // Change address modal in cart
    const changeAddressBtn = document.querySelector('.btn-change-address');
    if (changeAddressBtn) {
        changeAddressBtn.addEventListener('click', function() {
            openModal('change-address-modal');
        });
    }
    
    const confirmAddressBtn = document.getElementById('confirm-address');
    if (confirmAddressBtn) {
        confirmAddressBtn.addEventListener('click', function() {
            alert('Delivery address updated!');
            closeAllModals();
        });
    }
}

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

// Address Management
function openAddressModal(address = null) {
    const modal = document.getElementById('address-modal');
    const title = document.getElementById('modal-title');
    const form = document.getElementById('address-form');
    
    if (address) {
        // Edit mode
        title.textContent = 'Edit Address';
        document.getElementById('address-id').value = address.id;
        document.getElementById('address-label').value = address.label;
        document.getElementById('street-address').value = address.street;
        document.getElementById('city').value = address.city;
        document.getElementById('state').value = address.state;
        document.getElementById('zipcode').value = address.zipcode;
        document.getElementById('country').value = address.country;
        document.getElementById('phone').value = address.phone;
        document.getElementById('instructions').value = address.instructions || '';
        document.getElementById('set-default').checked = address.isDefault;
    } else {
        // Add mode
        title.textContent = 'Add New Address';
        form.reset();
        document.getElementById('address-id').value = '';
    }
    
    openModal('address-modal');
}

function saveAddress() {
    const addressId = document.getElementById('address-id').value;
    const label = document.getElementById('address-label').value;
    const street = document.getElementById('street-address').value;
    const city = document.getElementById('city').value;
    const state = document.getElementById('state').value;
    const zipcode = document.getElementById('zipcode').value;
    const country = document.getElementById('country').value;
    const phone = document.getElementById('phone').value;
    const instructions = document.getElementById('instructions').value;
    const isDefault = document.getElementById('set-default').checked;
    
    // Basic validation
    if (!label || !street || !city || !state || !zipcode || !country || !phone) {
        alert('Please fill in all required fields.');
        return;
    }
    
    alert('Address saved successfully!');
    closeAllModals();
    
    // In a real app, you would save to localStorage or send to server
    // and then refresh the addresses list
}

function editAddress(addressId) {
    // In a real app, you would fetch the address data
    const address = {
        id: addressId,
        label: 'Home',
        street: '123 Main Street, Apt 4B',
        city: 'New York',
        state: 'NY',
        zipcode: '10001',
        country: 'United States',
        phone: '+1 234 567 8900',
        instructions: 'Ring bell twice',
        isDefault: true
    };
    
    openAddressModal(address);
}

function deleteAddress(addressId) {
    if (confirm('Are you sure you want to delete this address?')) {
        alert('Address deleted successfully!');
        // In a real app, you would remove from storage and refresh the list
    }
}

function setDefaultAddress(addressId) {
    alert('Default address updated!');
    // In a real app, you would update the addresses in storage
}

// Restaurant Owner and Rider Applications
function handleRestaurantRegistration(e) {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('restaurant-name').value,
        cuisine: document.getElementById('cuisine-type').value,
        address: document.getElementById('address').value,
        phone: document.getElementById('phone').value,
        email: document.getElementById('email').value,
        description: document.getElementById('description').value
    };
    
    // Basic validation
    if (!formData.name || !formData.cuisine || !formData.address || !formData.phone || !formData.email) {
        alert('Please fill in all required fields.');
        return;
    }
    
    alert('Thank you for your application! We will review it and contact you soon.');
    e.target.reset();
}

function handleRiderApplication(e) {
    e.preventDefault();
    
    const formData = {
        firstName: document.getElementById('rider-first-name').value,
        lastName: document.getElementById('rider-last-name').value,
        email: document.getElementById('rider-email').value,
        phone: document.getElementById('rider-phone').value,
        city: document.getElementById('rider-city').value,
        vehicle: document.getElementById('vehicle-type').value
    };
    
    // Basic validation
    if (!formData.firstName || !formData.lastName || !formData.email || !formData.phone || !formData.city || !formData.vehicle) {
        alert('Please fill in all required fields.');
        return;
    }
    
    alert('Thank you for your application! We will review it and contact you soon.');
    e.target.reset();
}

// Utility Functions
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

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

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add loading states to buttons
document.querySelectorAll('button[type="submit"]').forEach(button => {
    button.addEventListener('click', function() {
        this.classList.add('loading');
        setTimeout(() => {
            this.classList.remove('loading');
        }, 2000);
    });
});

// Initialize when page loads
window.addEventListener('load', function() {
    console.log('Humsafar Food Delivery - Website loaded successfully!');
});