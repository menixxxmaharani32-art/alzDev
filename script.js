document.addEventListener('DOMContentLoaded', function() {
    console.log("ALZZ Development - Interactive Dynamic Script Loaded.");

    // 1. Efek Klik Neon Biru pada Banner / Logo Utama
    const heroBanner = document.getElementById('mainBanner');
    if (heroBanner) {
        heroBanner.addEventListener('click', function() {
            this.classList.add('neon-active');
            
            if ("vibrate" in navigator) {
                navigator.vibrate(40);
            }

            setTimeout(() => {
                this.classList.remove('neon-active');
            }, 1200);
        });
    }

    // 2. Animasi Muncul Berurutan (Staggered Fade-in) untuk Kartu Produk
    const cards = document.querySelectorAll('.app-card-mini');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(15px) scale(0.95)';
        card.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) ' + (index * 0.05) + 's';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0) scale(1)';
        }, 50);
    });

    // 3. Efek Interaktif Responsif & Sentuhan Getar pada Tombol Beli
    const buyButtons = document.querySelectorAll('.btn-buy-mini');
    buyButtons.forEach(button => {
        button.addEventListener('mousedown', function() {
            this.style.transform = 'scale(0.92)';
        });
        
        button.addEventListener('mouseup', function() {
            this.style.transform = 'scale(1)';
        });

        button.addEventListener('click', function() {
            if ("vibrate" in navigator) {
                navigator.vibrate(30);
            }
        });
    });

    // 4. Toggle Tombol Panah Bawah / Atas untuk Riwayat Pesanan
    const orderHeader = document.getElementById('orderHistoryHeader');
    const orderContent = document.getElementById('orderHistoryContent');
    const toggleIcon = document.getElementById('toggleIcon');

    if (orderHeader && orderContent && toggleIcon) {
        orderHeader.addEventListener('click', function() {
            orderContent.classList.toggle('collapsed');
            
            // Jika dikolaps (ditutup), ubah ikon jadi panah bawah (untuk melihat). Jika dibuka, jadi panah atas (untuk mengembalikan/menutup).
            if (orderContent.classList.contains('collapsed')) {
                toggleIcon.textContent = '▼'; 
            } else {
                toggleIcon.textContent = '▲'; 
            }

            if ("vibrate" in navigator) {
                navigator.vibrate(20);
            }
        });
    }
});
