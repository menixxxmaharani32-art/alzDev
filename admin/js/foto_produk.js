    // Pratinjau & perkecil foto produk dari galeri sebelum diunggah (supaya cepat & tidak gagal karena ukuran)
    (function () {
        var inp = document.getElementById('fotoProdukInput');
        var prev = document.getElementById('fotoProdukPreview');
        if (!inp) return;
        inp.addEventListener('change', function () {
            var f = inp.files && inp.files[0];
            if (!f) { prev.style.display = 'none'; return; }
            var url = URL.createObjectURL(f);
            prev.src = url; prev.style.display = 'block';
            if (!/^image\/(jpeg|png|webp)$/.test(f.type) || !window.DataTransfer) return;
            var img = new Image();
            img.onload = function () {
                try {
                    var max = 1200, w = img.naturalWidth, h = img.naturalHeight;
                    if (w <= max && h <= max && f.size < 1.5 * 1024 * 1024) return;
                    var r = Math.min(1, max / Math.max(w, h));
                    var c = document.createElement('canvas');
                    c.width = Math.round(w * r); c.height = Math.round(h * r);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    c.toBlob(function (b) {
                        if (!b) return;
                        var nf = new File([b], 'produk.jpg', { type: 'image/jpeg' });
                        var dt = new DataTransfer(); dt.items.add(nf); inp.files = dt.files;
                    }, 'image/jpeg', 0.85);
                } catch (e) {}
            };
            img.src = url;
        });
    })();
