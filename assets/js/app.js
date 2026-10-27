document.addEventListener('DOMContentLoaded', () => {
  // auto-dismiss alerts
  document.querySelectorAll('.auto-dismiss').forEach(a => setTimeout(() => bootstrap.Alert.getOrCreateInstance(a).close(), 6000));

  // confirm dialogs on forms: <form data-confirm="Are you sure?">
  document.querySelectorAll('form[data-confirm]').forEach(f =>
    f.addEventListener('submit', ev => { if (!confirm(f.dataset.confirm)) ev.preventDefault(); }));

  /* ---------- REAL-TIME STOCK SYNC (API integration) ----------
     Polls api/stock.php every 10 seconds so the storefront always shows live inventory
     even when the POS or another customer just bought the item. */
  const badges = document.querySelectorAll('.stock-badge');
  if (badges.length) {
    const refresh = async () => {
      try {
        const res = await fetch(window.EDM.base + '/api/stock.php', { cache: 'no-store' });
        const data = await res.json();
        const map = {}; data.items.forEach(i => map[i.sku] = i);
        badges.forEach(b => {
          const it = map[b.dataset.sku]; if (!it) return;
          b.className = 'badge stock-badge text-bg-' + it.color;
          b.textContent = it.status + (it.stock_qty > 0 ? ' (' + it.stock_qty + ')' : '');
          const btn = document.querySelector('button[data-add-for="' + b.dataset.sku + '"]');
          if (btn) btn.disabled = it.stock_qty <= 0;
        });
        const ts = document.getElementById('stock-updated');
        if (ts) ts.textContent = 'Live stock updated ' + new Date().toLocaleTimeString();
      } catch (e) { /* ignore network errors */ }
    };
    refresh(); setInterval(refresh, 10000);
  }

  /* ---------- POS terminal ---------- */
  const pos = document.getElementById('pos');
  if (pos) {
    const cart = {};
    const body = document.getElementById('ticket-body');
    const totalEl = document.getElementById('ticket-total');
    const hidden = document.getElementById('items-json');
    const peso = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const render = () => {
      body.innerHTML = ''; let total = 0; const payload = {};
      Object.values(cart).forEach(it => {
        total += it.price * it.qty; payload[it.id] = it.qty;
        const tr = document.createElement('tr');
        const name = document.createElement('td'); name.textContent = it.name;
        const qty = document.createElement('td'); qty.className = 'text-center text-nowrap';
        const mk = (t, fn) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-secondary py-0 px-2'; b.textContent = t; b.onclick = fn; return b; };
        const span = document.createElement('span'); span.className = 'mx-2'; span.textContent = it.qty;
        qty.append(mk('−', () => { it.qty--; if (it.qty <= 0) delete cart[it.id]; render(); }), span,
                   mk('+', () => { if (it.qty < it.stock) it.qty++; render(); }));
        const sum = document.createElement('td'); sum.className = 'text-end'; sum.textContent = peso(it.price * it.qty);
        tr.append(name, qty, sum); body.append(tr);
      });
      totalEl.textContent = peso(total);
      hidden.value = JSON.stringify(payload);
      document.getElementById('pay-btn').disabled = total <= 0;
    };
    pos.querySelectorAll('.pos-item').forEach(b => b.addEventListener('click', () => {
      const d = b.dataset, id = +d.id;
      if (!cart[id]) cart[id] = { id, name: d.name, price: +d.price, stock: +d.stock, qty: 0 };
      if (cart[id].qty < cart[id].stock) cart[id].qty++;
      render();
    }));
    document.getElementById('pos-search').addEventListener('input', ev => {
      const q = ev.target.value.toLowerCase();
      pos.querySelectorAll('.pos-col').forEach(c => c.style.display = c.dataset.search.includes(q) ? '' : 'none');
    });
    render();
  }
});
