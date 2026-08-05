// Deals Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeDealsPage();
    setupEventListeners();
});

// Sample Deals Data
const dealsData = [
    {
        id: 1,
        title: "Weekend Special",
        description: "Get 30% off on all orders above ₹299 every weekend",
        discount: "30% OFF",
        type: "discount",
        code: "WEEKEND30",
        minOrder: 299,
        validity: "Valid every Saturday & Sunday",
        featured: true
    },
    {
        id: 2,
        title: "Free Delivery",
        description: "Free delivery on all orders, no minimum required",
        discount: "FREE Delivery",
        type: "free-delivery",
        code: "FREESHIP",
        minOrder: 0,
        validity: "Valid until Dec 31, 2023",
        featured: false
    },
    {
        id: 3,
        title: "Family Combo",
        description: "Special family combo deal - save 25% on family packs",
        discount: "25% OFF",
        type: "combo",
        code: "FAMILY25",
        minOrder: 499,
        validity: "Valid until stock lasts",
        featured: true
    },
    {
        id: 4,
        title: "Cashback Offer",
        description: "Get 10% cashback on your next order",
        discount: "10% Cashback",
        type: "cashback",
        code: "CASHBACK10",
        minOrder: 199,
        validity: "Valid for 30 days",
        featured: false
    },
    {
        id: 5,
        title: "Lunch Special",
        description: "20% off on all lunch orders between 12 PM - 3 PM",
        discount: "20% OFF",
        type: "discount",
        code: "LUNCH20",
        minOrder: 199,
        validity: "Valid Mon-Fri, 12 PM - 3 PM",
        featured: false
    },
    {
        id: 6,
        title: "Student Discount",
        description: "Exclusive 15% discount for students with valid ID",
        discount: "15% OFF",
        type: "discount",
        code: "STUDENT15",
        minOrder: 149,
        validity: "Valid for verified students",
        featured: false
    }
];

function initializeDealsPage() {
    loadDeals();
    setupCategoryFilters();
}

function loadDeals() {
    const container = document.getElementById('deals-container');
    if (!container) return;

    container.innerHTML = dealsData.map(deal => `
        <div class="deal-card ${deal.featured ? 'featured' : ''}" data-category="${deal.type}">
            <div class="deal-header">
                <div class="deal-type-icon">
                    <i class="${getDealIcon(deal.type)}"></i>
                </div>
                <div class="deal-title">
                    <h4>${deal.title}</h4>
                    <p>${getDealTypeLabel(deal.type)}</p>
                </div>
            </div>
            
            <div class="deal-discount">${deal.discount}</div>
            
            <p class="deal-description">${deal.description}</p>
            
            <div class="deal-terms">
                <div class="term">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Min. order: ₹${deal.minOrder}</span>
                </div>
                <div class="term">
                    <i class="fas fa-calendar"></i>
                    <span>${deal.validity}</span>
                </div>
            </div>
            
            <div class="deal-footer">
                <div class="deal-code">
                    <span>Use code: </span>
                    <code>${deal.code}</code>
                    <button class="copy-btn" onclick="copyCode('${deal.code}')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function getDealIcon(type) {
    const icons = {
        'discount': 'fas fa-percentage',
        'free-delivery': 'fas fa-shipping-fast',
        'combo': 'fas fa-layer-group',
        'cashback': 'fas fa-rupee-sign',
        'first-order': 'fas fa-gift'
    };
    return icons[type] || 'fas fa-tag';
}

function getDealTypeLabel(type) {
    const labels = {
        'discount': 'Discount Offer',
        'free-delivery': 'Free Delivery',
        'combo': 'Combo Deal',
        'cashback': 'Cashback',
        'first-order': 'First Order'
    };
    return labels[type] || 'Special Offer';
}

function setupEventListeners() {
    // Load more button
    const loadMoreBtn = document.getElementById('load-more-btn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', loadMoreDeals);
    }

    // Category filters
    const filters = document.querySelectorAll('.category-filter');
    filters.forEach(filter => {
        filter.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            filterDeals(category);
            
            // Update active state
            filters.forEach(f => f.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

function setupCategoryFilters() {
    const filters = document.querySelectorAll('.category-filter');
    filters.forEach(filter => {
        filter.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            filterDeals(category);
            
            // Update active state
            filters.forEach(f => f.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

function filterDeals(category) {
    const deals = document.querySelectorAll('.deal-card');
    
    deals.forEach(deal => {
        if (category === 'all' || deal.getAttribute('data-category') === category) {
            deal.style.display = 'block';
        } else {
            deal.style.display = 'none';
        }
    });
}

function loadMoreDeals() {
    // In a real app, this would load more deals from an API
    const btn = document.getElementById('load-more-btn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    btn.disabled = true;
    
    setTimeout(() => {
        // Simulate loading more deals
        showToast('More deals loaded successfully!');
        btn.innerHTML = '<i class="fas fa-redo"></i> Load More Deals';
        btn.disabled = false;
    }, 1500);
}

function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        showToast('Code copied to clipboard!');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = code;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Code copied to clipboard!');
    });
}

function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.querySelector('span').textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Make functions available globally
window.copyCode = copyCode;
window.loadMoreDeals = loadMoreDeals;