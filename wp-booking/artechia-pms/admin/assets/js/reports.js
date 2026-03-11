/**
 * Artechia PMS Reports – Interactive charts & data display.
 */
document.addEventListener('DOMContentLoaded', function() {
    
    const api = artechiaPMS.restUrl + 'admin/reports/';
    const nonce = artechiaPMS.nonce;

    const tabs = document.querySelectorAll('.nav-tab');
    const contents = document.querySelectorAll('.artechia-tab-content');

    initTabs();
    loadDashboard();
    
    document.getElementById('btn-refresh-occ')?.addEventListener('click', loadOccupancy);
    document.getElementById('btn-refresh-fin')?.addEventListener('click', loadFinancial);
    document.getElementById('btn-refresh-src')?.addEventListener('click', loadSources);
    
    function initTabs() {
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const target = tab.getAttribute('data-tab');
                tabs.forEach(t => t.classList.remove('nav-tab-active'));
                tab.classList.add('nav-tab-active');
                contents.forEach(c => c.classList.remove('artechia-tab-content--active'));
                document.getElementById('tab-' + target).classList.add('artechia-tab-content--active');
                if (target === 'occupancy') loadOccupancy();
                if (target === 'financial') loadFinancial();
                if (target === 'sources') loadSources();
            });
        });
    }

    /* ── Dashboard ── */
    async function loadDashboard() {
        try {
            const res = await fetch( api + 'dashboard', { headers: { 'X-WP-Nonce': nonce } } );
            const data = await res.json();
            if ( ! res.ok ) throw new Error(data.message);
            renderCards(data);
            renderOccupancyChart(data.occupancy_trend);
            renderRevenueChart(data.revenue_trend);
        } catch (err) { console.error(err); }
    }

    function renderCards(data) {
        const container = document.getElementById('dashboard-cards');
        if(!container) return;
        container.innerHTML = `
            <div class="artechia-card artechia-card--primary">
                <div class="artechia-card__icon">🏨</div>
                <div class="artechia-card__body">
                    <span class="artechia-card__number">${data.occupancy_pct}%</span>
                    <span class="artechia-card__label">Ocupación Hoy (${data.occupied || 0}/${data.total_rooms || 0})</span>
                </div>
            </div>
             <div class="artechia-card artechia-card--success">
                <div class="artechia-card__icon">💰</div>
                <div class="artechia-card__body">
                    <span class="artechia-card__number">${formatMoney(data.revenue_month)}</span>
                    <span class="artechia-card__label">Ingresos Mes (Cobrados)</span>
                </div>
            </div>
             <div class="artechia-card artechia-card--warning">
                <div class="artechia-card__icon">⏳</div>
                <div class="artechia-card__body">
                    <span class="artechia-card__number">${formatMoney(data.outstanding)}</span>
                    <span class="artechia-card__label">Pendiente de Cobro</span>
                </div>
            </div>
             <div class="artechia-card artechia-card--info">
                <div class="artechia-card__icon">🔄</div>
                <div class="artechia-card__body">
                    <span class="artechia-card__number">${data.arrivals || 0} / ${data.departures || 0}</span>
                    <span class="artechia-card__label">Llegadas / Salidas Hoy</span>
                </div>
            </div>
        `;
    }

    /* ══════════════════════════════════════════
       INTERACTIVE LINE CHART
       ══════════════════════════════════════════ */
    function drawLineChart(canvasId, trendData, color, unit, isMoney) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !trendData || trendData.length === 0) return;
        
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const width = canvas.parentElement.getBoundingClientRect().width;
        const height = 250;
        
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        canvas.style.cursor = 'crosshair';
        ctx.scale(dpr, dpr);

        const padL = 65, padR = 20, padT = 20, padB = 40;
        const chartW = width - padL - padR;
        const chartH = height - padT - padB;

        // Max value — cap occupancy at 100%
        let maxVal = Math.max(...trendData.map(d => d.value));
        if (!isMoney && unit === '%') {
            maxVal = 100; // Always show full 0-100% scale for occupancy
        } else {
            if (maxVal === 0) maxVal = isMoney ? 10000 : 100;
            maxVal = Math.ceil(maxVal * 1.15);
        }

        const stepX = chartW / Math.max(1, trendData.length - 1);
        const points = trendData.map((p, i) => ({
            x: padL + (i * stepX),
            y: padT + chartH - ((Math.min(p.value, maxVal) / maxVal) * chartH),
            value: p.value,
            date: p.date
        }));

        function draw(hoverIdx) {
            ctx.clearRect(0, 0, width, height);

            // Grid
            const gridN = 5;
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.font = '11px -apple-system, BlinkMacSystemFont, sans-serif';
            for (let i = 0; i <= gridN; i++) {
                const val = (maxVal / gridN) * i;
                const y = padT + chartH - (chartH * (i / gridN));
                ctx.beginPath(); ctx.strokeStyle = '#f1f5f9'; ctx.lineWidth = 1;
                ctx.moveTo(padL, y); ctx.lineTo(width - padR, y); ctx.stroke();
                ctx.fillStyle = '#94a3b8';
                ctx.fillText(isMoney ? '$' + Math.round(val).toLocaleString() : Math.round(val) + unit, padL - 8, y);
            }

            // Area
            ctx.beginPath();
            ctx.moveTo(padL, padT + chartH);
            points.forEach(p => ctx.lineTo(p.x, p.y));
            ctx.lineTo(points[points.length - 1].x, padT + chartH);
            ctx.closePath();
            const grad = ctx.createLinearGradient(0, padT, 0, padT + chartH);
            grad.addColorStop(0, color + '30');
            grad.addColorStop(1, color + '05');
            ctx.fillStyle = grad;
            ctx.fill();

            // Line
            ctx.beginPath(); ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineJoin = 'round';
            points.forEach((p, i) => { i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y); });
            ctx.stroke();

            // Dots
            points.forEach((p, i) => {
                const isHover = (i === hoverIdx);
                ctx.beginPath();
                ctx.arc(p.x, p.y, isHover ? 5 : 3, 0, Math.PI * 2);
                ctx.fillStyle = '#fff';
                ctx.strokeStyle = color;
                ctx.lineWidth = isHover ? 3 : 2;
                ctx.fill(); ctx.stroke();
            });

            // Crosshair on hover
            if (hoverIdx !== null && hoverIdx >= 0) {
                const hp = points[hoverIdx];
                ctx.beginPath();
                ctx.strokeStyle = color + '40';
                ctx.lineWidth = 1;
                ctx.setLineDash([4, 3]);
                ctx.moveTo(hp.x, padT); ctx.lineTo(hp.x, padT + chartH);
                ctx.stroke();
                ctx.setLineDash([]);

                // Horizontal dashed line to Y axis
                ctx.beginPath();
                ctx.strokeStyle = color + '30';
                ctx.setLineDash([4, 3]);
                ctx.moveTo(padL, hp.y); ctx.lineTo(hp.x, hp.y);
                ctx.stroke();
                ctx.setLineDash([]);
            }

            // X labels
            ctx.textAlign = 'center'; ctx.textBaseline = 'top';
            ctx.fillStyle = '#94a3b8';
            ctx.font = '10px -apple-system, BlinkMacSystemFont, sans-serif';
            const skip = Math.ceil(points.length / 10);
            points.forEach((p, i) => {
                if (i % skip !== 0 && i !== points.length - 1) return;
                const d = new Date(p.date);
                ctx.fillText(d.getDate() + '/' + (d.getMonth() + 1), p.x, padT + chartH + 8);
            });
        }

        draw(null);

        // Tooltip element
        let tip = canvas.parentElement.querySelector('.chart-tip');
        if (tip) tip.remove();
        tip = document.createElement('div');
        tip.className = 'chart-tip';
        tip.style.cssText = 'position:absolute;pointer-events:none;background:rgba(15,23,42,.92);color:#fff;padding:8px 12px;border-radius:8px;font-size:12px;line-height:1.5;opacity:0;transition:opacity .15s;z-index:10;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.25);';
        canvas.parentElement.style.position = 'relative';
        canvas.parentElement.appendChild(tip);

        canvas.onmousemove = function(e) {
            const r = canvas.getBoundingClientRect();
            const mx = e.clientX - r.left;
            let closest = 0, minD = Infinity;
            points.forEach((p, i) => { const d = Math.abs(p.x - mx); if (d < minD) { minD = d; closest = i; } });
            if (minD > stepX * 1.5) { draw(null); tip.style.opacity = '0'; return; }

            draw(closest);
            const p = points[closest];
            const dt = new Date(p.date);
            const dateStr = dt.getDate() + '/' + (dt.getMonth()+1) + '/' + dt.getFullYear();
            const valStr = isMoney
                ? '$' + parseFloat(p.value).toLocaleString('es-AR', {minimumFractionDigits:0})
                : Math.round(p.value) + unit;
            tip.innerHTML = `<div style="font-weight:600;margin-bottom:2px;">${dateStr}</div><div style="font-size:14px;font-weight:700;color:${color}">${valStr}</div>`;
            let tx = p.x + 14, ty = p.y - 50;
            if (tx + 130 > width) tx = p.x - 140;
            if (ty < 5) ty = 10;
            tip.style.left = tx + 'px';
            tip.style.top = ty + 'px';
            tip.style.opacity = '1';
        };
        canvas.onmouseleave = function() { draw(null); tip.style.opacity = '0'; };
    }

    function renderOccupancyChart(td) { drawLineChart('chart-occupancy', td, '#3b82f6', '%', false); }
    function renderRevenueChart(td) { drawLineChart('chart-revenue', td, '#10b981', '', true); }

    /* ══════════════════════════════════════════
       OCCUPANCY REPORT
       ══════════════════════════════════════════ */
    async function loadOccupancy() {
        const start = document.getElementById('occ-start')?.value;
        const end = document.getElementById('occ-end')?.value;
        const roomTypeFilter = document.getElementById('occ-room-type')?.value || '';
        
        const exportBtn = document.getElementById('btn-export-occ');
        if(exportBtn) exportBtn.href = `${artechiaPMS.adminUrl}admin-post.php?action=artechia_export_report&type=occupancy&start=${start}&end=${end}&room_type_id=${roomTypeFilter}`;

        try {
            const res = await fetch( `${api}occupancy?start_date=${start}&end_date=${end}&room_type_id=${roomTypeFilter}`, { headers: { 'X-WP-Nonce': nonce } } );
            const data = await res.json();
             
             const summary = document.getElementById('occ-summary');
             if(summary) {
                 summary.innerHTML = `
                    <div class="artechia-stat-box">
                        <span class="stat-label">🏨 Ocupación</span>
                        <span class="stat-value">${data.occupancy_pct}%</span>
                    </div>
                    <div class="artechia-stat-box">
                        <span class="stat-label">🛏️ Noches Vendidas</span>
                        <span class="stat-value">${Math.round(data.nights_sold)} / ${Math.round(data.capacity_nights || 0)}</span>
                    </div>
                    <div class="artechia-stat-box">
                        <span class="stat-label">💵 ADR</span>
                        <span class="stat-value">${formatMoney(data.adr)}</span>
                    </div>
                    <div class="artechia-stat-box">
                        <span class="stat-label">📊 RevPAR</span>
                        <span class="stat-value">${formatMoney(data.revpar)}</span>
                    </div>
                     <div class="artechia-stat-box">
                        <span class="stat-label">💰 Ingresos Cobrados</span>
                        <span class="stat-value artechia-text-success">${formatMoney(data.revenue_generated)}</span>
                    </div>
                 `;
             }

             const tbody = document.querySelector('#table-occupancy-breakdown tbody');
             if ( tbody ) {
                 tbody.innerHTML = '';
                 if ( data.breakdown && data.breakdown.length > 0 ) {
                     data.breakdown.forEach(row => {
                         const tr = document.createElement('tr');
                         const pctColor = row.occupancy_pct >= 70 ? '#16a34a' : row.occupancy_pct >= 40 ? '#f59e0b' : '#dc2626';
                         tr.innerHTML = `
                             <td><strong>${row.room_type_name}</strong></td>
                             <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="width:60px; height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden;">
                                        <div style="width:${Math.min(100, row.occupancy_pct)}%; height:100%; background:${pctColor}; border-radius:4px;"></div>
                                    </div>
                                    <span style="font-weight:600; color:${pctColor};">${row.occupancy_pct}%</span>
                                </div>
                             </td>
                             <td>${Math.round(row.nights_sold)} / ${row.capacity_nights}</td>
                             <td>${formatMoney(row.adr)}</td>
                             <td style="font-weight:600;">${formatMoney(row.revenue)}</td>
                         `;
                         tbody.appendChild(tr);
                     });
                 } else {
                     tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#94a3b8;">No hay datos para este período.</td></tr>';
                 }
             }
        } catch(e) { console.error(e); }
    }

    /* ══════════════════════════════════════════
       FINANCIAL REPORT
       ══════════════════════════════════════════ */
    async function loadFinancial() {
        const start = document.getElementById('fin-start')?.value;
        const end = document.getElementById('fin-end')?.value;

        const exportBtn = document.getElementById('btn-export-fin');
        if(exportBtn) exportBtn.href = `${artechiaPMS.adminUrl}admin-post.php?action=artechia_export_report&type=financial&start=${start}&end=${end}`;

        try {
            const res = await fetch( `${api}financial?start_date=${start}&end_date=${end}`, { headers: { 'X-WP-Nonce': nonce } } );
            const data = await res.json();
            
            const tbody = document.querySelector('#table-financial tbody');
            tbody.innerHTML = '';
            
            if (data.breakdown && data.breakdown.length > 0) {
                const gwLabels = {
                    'manual': 'Manual', 'mercadopago': 'Mercado Pago', 
                    'efectivo': 'Efectivo', 'transferencia': 'Transferencia',
                    'tarjeta': 'Tarjeta', 'cash': 'Efectivo', 'transfer': 'Transferencia',
                    'card': 'Tarjeta', 'cheque': 'Cheque', 'paypal': 'PayPal'
                };
                const modeLabels = {
                    'manual': 'Manual', 'online': 'Online', 'redirect': 'Redirección',
                    'deposit': 'Seña/Depósito'
                };
                data.breakdown.forEach(row => {
                    const tr = document.createElement('tr');
                    const gwLabel = gwLabels[row.gateway] || row.gateway || 'Sin datos';
                    const modeLabel = modeLabels[row.pay_mode] || row.pay_mode || '—';
                    tr.innerHTML = `
                        <td><strong>${gwLabel}</strong></td>
                        <td>${modeLabel}</td>
                        <td>${row.txn_count}</td>
                        <td style="font-weight:600;">${formatMoney(row.total)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">No hay transacciones en este período.</td></tr>';
            }
            
            const elTotal = document.getElementById('fin-total-collected');
            const elAccom = document.getElementById('fin-total-accom');
            const elExtra = document.getElementById('fin-total-extras');
            if(elTotal) elTotal.textContent = formatMoney(data.total_collected || 0);
            if(elAccom) elAccom.textContent = formatMoney(data.accommodation_revenue || 0);
            if(elExtra) elExtra.textContent = formatMoney(data.extras_revenue || 0);

        } catch(e) { console.error(e); }
    }
    
    /* ══════════════════════════════════════════
       SOURCES REPORT — INTERACTIVE BAR CHART
       ══════════════════════════════════════════ */
    async function loadSources() {
        const start = document.getElementById('src-start')?.value;
        const end = document.getElementById('src-end')?.value;

        try {
            const res = await fetch( `${api}sources?start_date=${start}&end_date=${end}`, { headers: { 'X-WP-Nonce': nonce } } );
            const data = await res.json();
            
            const tbody = document.querySelector('#table-sources tbody');
            tbody.innerHTML = '';
            
            if (Array.isArray(data) && data.length > 0) {
                const totalBookings = data.reduce((s, r) => s + parseInt(r.count || 0), 0);
                data.forEach(row => {
                    const tr = document.createElement('tr');
                    const pct = totalBookings > 0 ? Math.round((parseInt(row.count) / totalBookings) * 100) : 0;
                    tr.innerHTML = `
                        <td>
                            <strong>${row.source || 'Directa / Admin'}</strong>
                            <div style="font-size:11px; color:#94a3b8;">${pct}% del total</div>
                        </td>
                        <td>${row.count}</td>
                        <td style="font-weight:600;">${formatMoney(row.revenue)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#94a3b8;">No hay datos para este período.</td></tr>';
            }
            
            renderSourcesChart(data);
        } catch(e) { console.error(e); }
    }

    function renderSourcesChart(chartData) {
        const canvas = document.getElementById('chart-sources');
        if (!canvas) return;
        
        const container = canvas.parentElement;
        const dpr = window.devicePixelRatio || 1;
        const width = container.clientWidth;
        const height = container.clientHeight || 300;
        
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        canvas.style.cursor = 'pointer';
        
        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        if (!chartData || !Array.isArray(chartData) || chartData.length === 0) {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '14px -apple-system, BlinkMacSystemFont, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sin datos', width / 2, height / 2);
            return;
        }

        const colors = ['#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4', '#f97316'];
        const padL = 70, padR = 20, padT = 20, padB = 50;
        const chartW = width - padL - padR;
        const chartH = height - padT - padB;

        let maxVal = Math.max(...chartData.map(d => parseFloat(d.revenue || 0)));
        if (maxVal === 0) maxVal = 1000;
        maxVal *= 1.15;

        const barPadding = Math.max(8, 30 / chartData.length);
        const barWidth = Math.min(60, (chartW / chartData.length) - barPadding);
        const totalW = barWidth + barPadding;
        const startXOffset = (chartW - (chartData.length * totalW)) / 2;

        // Pre-calculate bars
        const bars = chartData.map((point, i) => {
            const val = parseFloat(point.revenue || 0);
            const x = padL + startXOffset + (i * totalW) + (barPadding / 2);
            const barH = (val / maxVal) * chartH;
            const y = padT + chartH - barH;
            return { x, y, w: barWidth, h: barH, val, source: point.source || 'Directa', count: point.count, color: colors[i % colors.length] };
        });

        function drawBars(hoverIdx) {
            ctx.clearRect(0, 0, width, height);

            // Grid
            const gridN = 4;
            ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
            ctx.font = '10px -apple-system, BlinkMacSystemFont, sans-serif';
            for (let i = 0; i <= gridN; i++) {
                const val = (maxVal / gridN) * i;
                const y = padT + chartH - (chartH * (i / gridN));
                ctx.beginPath(); ctx.strokeStyle = '#f1f5f9'; ctx.lineWidth = 1;
                ctx.moveTo(padL, y); ctx.lineTo(width - padR, y); ctx.stroke();
                ctx.fillStyle = '#94a3b8';
                ctx.fillText('$' + Math.round(val).toLocaleString(), padL - 8, y);
            }

            bars.forEach((b, i) => {
                const isHover = (i === hoverIdx);
                const radius = Math.min(4, b.w / 2);
                
                // Shadow on hover
                if (isHover) {
                    ctx.shadowColor = b.color + '40';
                    ctx.shadowBlur = 12;
                    ctx.shadowOffsetY = 4;
                }

                ctx.fillStyle = isHover ? b.color : b.color + (isHover ? '' : 'cc');
                ctx.beginPath();
                ctx.moveTo(b.x, padT + chartH);
                ctx.lineTo(b.x, b.y + radius);
                ctx.quadraticCurveTo(b.x, b.y, b.x + radius, b.y);
                ctx.lineTo(b.x + b.w - radius, b.y);
                ctx.quadraticCurveTo(b.x + b.w, b.y, b.x + b.w, b.y + radius);
                ctx.lineTo(b.x + b.w, padT + chartH);
                ctx.closePath();
                ctx.fill();

                ctx.shadowColor = 'transparent';
                ctx.shadowBlur = 0;
                ctx.shadowOffsetY = 0;

                // Value on top
                if (b.val > 0) {
                    ctx.fillStyle = isHover ? '#0f172a' : '#64748b';
                    ctx.font = (isHover ? 'bold ' : '') + '10px -apple-system, BlinkMacSystemFont, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText('$' + Math.round(b.val).toLocaleString(), b.x + (b.w / 2), b.y - 8);
                }

                // Source label
                ctx.fillStyle = isHover ? '#0f172a' : '#64748b';
                ctx.font = (isHover ? 'bold ' : '') + '11px -apple-system, BlinkMacSystemFont, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(b.source.substring(0, 12), b.x + (b.w / 2), padT + chartH + 14);

                // Count
                ctx.fillStyle = '#94a3b8';
                ctx.font = '10px -apple-system, BlinkMacSystemFont, sans-serif';
                ctx.fillText(b.count + ' res.', b.x + (b.w / 2), padT + chartH + 28);
            });
        }

        drawBars(null);

        // Tooltip
        let tip = container.querySelector('.chart-tip-bar');
        if (tip) tip.remove();
        tip = document.createElement('div');
        tip.className = 'chart-tip-bar';
        tip.style.cssText = 'position:absolute;pointer-events:none;background:rgba(15,23,42,.92);color:#fff;padding:8px 12px;border-radius:8px;font-size:12px;line-height:1.5;opacity:0;transition:opacity .15s;z-index:10;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.25);';
        container.style.position = 'relative';
        container.appendChild(tip);

        canvas.onmousemove = function(e) {
            const r = canvas.getBoundingClientRect();
            const mx = e.clientX - r.left, my = e.clientY - r.top;
            let hoverIdx = null;
            bars.forEach((b, i) => {
                if (mx >= b.x && mx <= b.x + b.w && my >= b.y && my <= padT + chartH) hoverIdx = i;
            });

            drawBars(hoverIdx);

            if (hoverIdx !== null) {
                const b = bars[hoverIdx];
                tip.innerHTML = `<div style="font-weight:600;">${b.source}</div><div style="font-size:14px;font-weight:700;color:${b.color}">$${Math.round(b.val).toLocaleString()}</div><div style="color:#94a3b8">${b.count} reservas</div>`;
                let tx = b.x + b.w + 8, ty = b.y - 20;
                if (tx + 130 > width) tx = b.x - 140;
                if (ty < 5) ty = 10;
                tip.style.left = tx + 'px';
                tip.style.top = ty + 'px';
                tip.style.opacity = '1';
            } else {
                tip.style.opacity = '0';
            }
        };
        canvas.onmouseleave = function() { drawBars(null); tip.style.opacity = '0'; };
    }

    function formatMoney(amount) {
        if (artechiaPMS && artechiaPMS.formatPrice) return artechiaPMS.formatPrice(amount);
        return parseFloat(amount || 0).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

});
