// Deposit saldo (pembeli) + pengaturan pembayaran dan persetujuan deposit (admin)
module.exports = function mountDeposit(app, d) {
  const { rest, rpc, getSettings, saveSettings, adminOnly, buyerOnly, run, enc, notify } = d;
  const MIN = 1000;
  const MAX = 10000000;
  const IMG = /^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$/;
  const validImg = (s, max) => typeof s === 'string' && s.length <= max && IMG.test(s);
  const idOf = (req) => parseInt(req.params.id, 10) || 0;

  async function payment() {
    const s = await getSettings();
    const [q] = await rest('settings', '?select=value&key=eq.pay_qris');
    return { dana: s.pay_dana || '', dana_name: s.pay_dana_name || '', qris: (q && q.value) || '' };
  }

  /* ---------- Pembeli ---------- */
  app.get('/api/buyer/payment', buyerOnly, run(async () => ({ status: true, ...(await payment()) })));

  // Langkah 1: ajukan nominal dulu (tanpa bukti). Bukti diunggah di langkah berikutnya.
  app.post('/api/buyer/deposit', buyerOnly, run(async (req) => {
    const amount = parseInt((req.body || {}).amount, 10);
    if (!(amount >= MIN && amount <= MAX)) return { status: false, error: 'Jumlah deposit minimal Rp 1.000 dan maksimal Rp 10.000.000.' };
    const pending = await rest('deposits', '?select=id&status=eq.pending&user_id=eq.' + enc(req.buyerId));
    if (pending.length >= 3) return { status: false, error: 'Anda masih punya 3 deposit yang menunggu. Lanjutkan atau tunggu admin memprosesnya.' };
    const [dep] = await rest('deposits', '?select=id', { method: 'POST', prefer: 'return=representation', body: { user_id: req.buyerId, amount, proof: null, has_proof: false } });
    return { status: true, id: dep && dep.id };
  }));

  // Langkah 2: unggah bukti transfer untuk deposit milik pembeli yang masih pending.
  app.post('/api/buyer/deposit/:id/proof', buyerOnly, run(async (req) => {
    const id = idOf(req);
    const proof = (req.body || {}).proof;
    if (!id) return { status: false, error: 'ID tidak valid.' };
    if (!proof) return { status: false, error: 'Bukti transfer wajib diunggah.' };
    if (!validImg(proof, 600000)) return { status: false, error: 'Bukti transfer harus berupa foto (JPG, PNG, atau WEBP) yang tidak terlalu besar.' };
    const rows = await rest('deposits', '?select=id,amount&status=eq.pending&id=eq.' + id + '&user_id=eq.' + enc(req.buyerId), { method: 'PATCH', prefer: 'return=representation', body: { proof, has_proof: true } });
    if (rows && rows.length) {
      try { // notifikasi ke HP admin: deposit siap diperiksa
        const [u] = await rest('users', '?select=username&id=eq.' + enc(req.buyerId));
        await notify('deposit', '💰 Deposit baru', ((u && u.username) || 'Pembeli') + ' mengajukan deposit Rp ' + Math.round(rows[0].amount).toLocaleString('id-ID') + ' (bukti sudah diunggah)', '/admin#dp');
      } catch (e) { console.error('notif deposit:', e.message); }
    }
    return rows && rows.length ? { status: true } : { status: false, error: 'Deposit tidak ditemukan atau sudah diproses.' };
  }));


  app.get('/api/buyer/deposits', buyerOnly, run(async (req) => ({
    status: true,
    data: await rest('deposits', '?select=id,amount,status,has_proof,created_at&order=id.desc&limit=20&user_id=eq.' + enc(req.buyerId)),
  })));

  /* ---------- Admin: pengaturan pembayaran ---------- */
  app.get('/api/admin/payment', adminOnly, run(async () => ({ status: true, ...(await payment()) })));

  app.post('/api/admin/payment', adminOnly, run(async (req, res) => {
    const b = req.body || {};
    const dana = String(b.dana || '').trim();
    const name = String(b.dana_name || '').trim();
    if (!/^[0-9+\-\s]{0,20}$/.test(dana)) { res.status(400); return { status: false, error: 'Nomor Dana hanya boleh berisi angka (maksimal 20 karakter).' }; }
    if (name.length > 60) { res.status(400); return { status: false, error: 'Nama terlalu panjang (maksimal 60 karakter).' }; }
    const data = { pay_dana: dana, pay_dana_name: name };
    if (b.remove_qris) data.pay_qris = '';
    else if (b.qris) {
      if (!validImg(b.qris, 800000)) { res.status(400); return { status: false, error: 'Foto QRIS harus JPG, PNG, atau WEBP dan tidak terlalu besar.' }; }
      data.pay_qris = b.qris;
    }
    await saveSettings(data);
    return { status: true };
  }));

  /* ---------- Admin: persetujuan deposit ---------- */
  async function withNames(rows) {
    const ids = [...new Set(rows.map((r) => r.user_id))];
    const names = {};
    if (ids.length) (await rest('users', '?select=id,username&id=in.(' + ids.map(enc).join(',') + ')')).forEach((u) => { names[u.id] = u.username; });
    return rows.map((r) => ({ id: r.id, username: names[r.user_id] || '(akun dihapus)', amount: r.amount, status: r.status, has_proof: r.has_proof, created_at: r.created_at }));
  }

  app.get('/api/admin/deposits', adminOnly, run(async (req) => {
    const done = req.query.status === 'done';
    const q = '?select=id,user_id,amount,status,has_proof,created_at&' +
      (done ? 'status=in.(approved,rejected)&order=id.desc&limit=30' : 'status=eq.pending&order=id.asc&limit=100');
    return { status: true, data: await withNames(await rest('deposits', q)) };
  }));

  app.get('/api/admin/deposits/:id/proof', adminOnly, run(async (req) => {
    const id = idOf(req);
    if (!id) return { status: false, error: 'ID tidak valid.' };
    const [r] = await rest('deposits', '?select=proof&id=eq.' + id);
    return r && r.proof ? { status: true, proof: r.proof } : { status: false, error: 'Tidak ada bukti transfer.' };
  }));

  // Klaim dulu (hanya jika masih pending) supaya tidak bisa diproses dua kali.
  const claim = (id, to) => rest('deposits', '?select=id,user_id,amount&status=eq.pending&id=eq.' + id + (to === 'approved' ? '&has_proof=eq.true' : ''), {
    method: 'PATCH', prefer: 'return=representation', body: { status: to, decided_at: new Date().toISOString() },
  });

  app.post('/api/admin/deposits/:id/approve', adminOnly, run(async (req) => {
    const id = idOf(req);
    if (!id) return { status: false, error: 'ID tidak valid.' };
    const [dep] = (await claim(id, 'approved')) || [];
    if (!dep) return { status: false, error: 'Deposit belum ada bukti transfer, sudah diproses, atau tidak ditemukan.' };
    try {
      const nb = await rpc('adjust_balance', { p_user: dep.user_id, p_delta: dep.amount });
      if (nb === null) throw new Error('Saldo gagal ditambahkan');
      return { status: true, balance: nb };
    } catch (e) {
      await rest('deposits', '?id=eq.' + id, { method: 'PATCH', body: { status: 'pending', decided_at: null } }).catch(() => {});
      throw e;
    }
  }));

  app.post('/api/admin/deposits/:id/reject', adminOnly, run(async (req) => {
    const id = idOf(req);
    if (!id) return { status: false, error: 'ID tidak valid.' };
    const [dep] = (await claim(id, 'rejected')) || [];
    return dep ? { status: true } : { status: false, error: 'Deposit sudah diproses atau tidak ditemukan.' };
  }));
};
