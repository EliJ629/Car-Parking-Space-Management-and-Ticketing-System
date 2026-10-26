// Bayline Booth Attendant Console Scripts
document.addEventListener('DOMContentLoaded', () => {
  const shell = document.getElementById('ba-shell');
  if (!shell) return;

  const csrf = shell.dataset.csrf;
  const alertsUrl = shell.dataset.alertsUrl;
  const alertsReadUrl = shell.dataset.alertsRead;
  const keepaliveUrl = shell.dataset.keepalive;
  const loginUrl = shell.dataset.login;
  const idleMinutes = parseInt(shell.dataset.idle || '15', 10);

  // 1. Live Clock
  const clockEl = document.getElementById('clock');
  if (clockEl) {
    setInterval(() => {
      const now = new Date();
      const opts = { timeZone: 'Asia/Manila', weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
      const timeOpts = { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', hour12: true };
      const dateStr = now.toLocaleDateString('en-US', opts);
      const timeStr = now.toLocaleTimeString('en-US', timeOpts);
      clockEl.innerHTML = `${dateStr} <b>${timeStr}</b>`;
    }, 1000);
  }

  // 2. Dialog open/close handlers
  document.querySelectorAll('[data-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.querySelector(btn.dataset.open);
      if (target && typeof target.showModal === 'function') {
        target.showModal();
      }
    });
  });

  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const dlg = btn.closest('dialog');
      if (dlg && typeof dlg.close === 'function') {
        dlg.close();
      }
    });
  });

  // Auto-open modal if requested by session
  document.querySelectorAll('dialog.auto-open').forEach(dlg => {
    if (typeof dlg.showModal === 'function') {
      dlg.showModal();
    }
  });

  // Reject dialog: the text box shows only when "Other" is chosen.
  document.querySelectorAll('[data-reject-reason]').forEach(select => {
    const other = select.closest('form').querySelector('[data-reject-other]');
    select.addEventListener('change', () => {
      const show = select.value === 'other';
      other.hidden = !show;
      const input = other.querySelector('input');
      input.required = show;
      if (show) input.focus();
    });
  });

  // 3. Auto-dismiss toasts
  document.querySelectorAll('.ba-toast').forEach(toast => {
    setTimeout(() => {
      toast.style.transition = 'opacity .3s';
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, 5000);
  });

  // 4. Alerts polling & Mark all as read
  const alertBtn = document.getElementById('alert-btn');
  const alertCount = document.getElementById('alert-count');
  const alertList = document.getElementById('alert-list');
  const markReadBtn = document.getElementById('alerts-mark-read');

  const renderAlertItems = (items) => {
    if (!alertList) return;
    alertList.innerHTML = '';
    if (!items || !items.length) {
      const noneLi = document.createElement('li');
      noneLi.className = 'none';
      noneLi.textContent = 'No alerts right now.';
      alertList.appendChild(noneLi);
      return;
    }
    items.forEach(a => {
      const li = document.createElement('li');
      if (a.unread) li.className = 'unread';
      const link = document.createElement('a');
      link.href = a.url;
      const b = document.createElement('b');
      b.textContent = a.title;
      const span = document.createElement('span');
      span.textContent = a.body;
      const small = document.createElement('small');
      small.textContent = a.time;
      link.appendChild(b);
      link.appendChild(span);
      link.appendChild(small);
      li.appendChild(link);
      alertList.appendChild(li);
    });
  };

  if (markReadBtn && alertsReadUrl) {
    markReadBtn.addEventListener('click', async () => {
      try {
        await fetch(alertsReadUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        if (alertCount) {
          alertCount.hidden = true;
          alertCount.textContent = '0';
        }
        document.querySelectorAll('.ba-alerts li.unread').forEach(li => li.classList.remove('unread'));
      } catch (err) {
        console.error('Failed to mark alerts read', err);
      }
    });
  }

  // Poll alerts every 10 seconds
  if (alertsUrl) {
    setInterval(async () => {
      try {
        const res = await fetch(alertsUrl, { headers: { 'Accept': 'application/json' } });
        if (res.ok) {
          const data = await res.json();
          if (alertCount) {
            alertCount.textContent = data.count;
            alertCount.hidden = data.count < 1;
          }
          if (data.items) {
            renderAlertItems(data.items);
          }
        }
      } catch (e) {}
    }, 10000);
  }

  // 5. Idle Session Timeout (BA-09)
  let idleTime = 0;
  const idleDlg = document.getElementById('idle-dlg');
  const idleLeft = document.getElementById('idle-left');
  const timeoutDlg = document.getElementById('timeout-dlg');
  let countdownSec = 60;
  let countdownTimer = null;

  const resetIdle = () => {
    idleTime = 0;
  };
  ['mousemove', 'keydown', 'click', 'scroll'].forEach(evt => window.addEventListener(evt, resetIdle, { passive: true }));

  setInterval(() => {
    idleTime += 1;
    const warningThreshold = (idleMinutes * 60) - 60;

    if (idleTime >= warningThreshold && !countdownTimer) {
      if (idleDlg && typeof idleDlg.showModal === 'function') {
        countdownSec = 60;
        idleDlg.showModal();
        countdownTimer = setInterval(() => {
          countdownSec -= 1;
          if (idleLeft) idleLeft.textContent = countdownSec;
          if (countdownSec <= 0) {
            clearInterval(countdownTimer);
            if (idleDlg.close) idleDlg.close();
            if (timeoutDlg && timeoutDlg.showModal) timeoutDlg.showModal();
          }
        }, 1000);
      }
    }
  }, 1000);

  const idleStay = document.getElementById('idle-stay');
  if (idleStay) {
    idleStay.addEventListener('click', async () => {
      if (countdownTimer) clearInterval(countdownTimer);
      countdownTimer = null;
      if (idleDlg.close) idleDlg.close();
      idleTime = 0;
      if (keepaliveUrl) {
        await fetch(keepaliveUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } });
      }
    });
  }

  // Toast notification helper
  window.baShowToast = function (title, message, isSuccess = true) {
    let container = document.getElementById('ba-toasts');
    if (!container) {
      container = document.createElement('div');
      container.id = 'ba-toasts';
      container.className = 'ba-toasts';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'ba-toast';
    toast.style.borderLeft = isSuccess ? '4px solid #22C55E' : '4px solid #EF4444';
    toast.innerHTML = `<b style="display:flex;align-items:center;gap:6px;">${title}</b><span style="opacity:.9;">${message || ''}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.transition = 'opacity .35s ease, transform .35s ease';
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(50px)';
      setTimeout(() => toast.remove(), 350);
    }, 4000);
  };

  // 6. Thermal Print helper (Optimized for 58mm thermal printer roll)
  window.baPrint = function (selector) {
    const target = selector
      ? document.querySelector(selector)
      : (document.querySelector('dialog[open] .ba-thermal') || document.querySelector('.ba-thermal'));

    if (!target) {
      window.print();
      return;
    }

    // Close preview modal if open so the attendant returns cleanly to the screen
    const openDialog = target.closest('dialog') || document.querySelector('dialog[open]');
    if (openDialog && typeof openDialog.close === 'function') {
      openDialog.close();
    }

    const text = (target.innerText || selector || '').toLowerCase();
    const isReceipt = text.includes('receipt') || text.includes('official');
    const docName = isReceipt ? 'Official receipt' : 'Parking ticket';

    // Show in-app toast notification
    window.baShowToast(
      `🖨️ Printing ${docName}...`,
      `Sent to thermal printer queue.`
    );

    // Call page-specific callbacks to mark as printed
    if (typeof markReceiptPrinted === 'function') markReceiptPrinted();

    // Fire window event
    window.dispatchEvent(new CustomEvent('ba:printed', { detail: { docName, isReceipt } }));

    // Clone element and preserve QR code canvases as images
    const clone = target.cloneNode(true);
    const origCanvases = target.querySelectorAll('canvas');
    const cloneCanvases = clone.querySelectorAll('canvas');
    origCanvases.forEach((c, idx) => {
      try {
        const img = document.createElement('img');
        img.src = c.toDataURL('image/png');
        img.style.width = '110px';
        img.style.height = '110px';
        img.style.display = 'block';
        img.style.margin = '0 auto';
        if (cloneCanvases[idx]) {
          cloneCanvases[idx].parentNode.replaceChild(img, cloneCanvases[idx]);
        }
      } catch (e) {
        console.warn('Canvas toDataURL failed', e);
      }
    });

    // Create or reuse an isolated hidden iframe
    let printFrame = document.getElementById('ba-print-iframe');
    if (printFrame) {
      printFrame.remove();
    }
    printFrame = document.createElement('iframe');
    printFrame.id = 'ba-print-iframe';
    printFrame.style.position = 'fixed';
    printFrame.style.right = '0';
    printFrame.style.bottom = '0';
    printFrame.style.width = '0';
    printFrame.style.height = '0';
    printFrame.style.border = '0';
    printFrame.style.visibility = 'hidden';
    document.body.appendChild(printFrame);

    const frameDoc = printFrame.contentWindow.document;
    frameDoc.open();
    frameDoc.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Bayline</title>
  <style>
    @page {
      size: 58mm auto;
      margin: 0mm;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      color: #000 !important;
      background: transparent !important;
      font-family: 'Consolas', 'Lucida Console', 'Courier New', monospace !important;
      font-weight: 700 !important;
    }
    html, body {
      width: 58mm;
      max-width: 58mm;
      margin: 0;
      padding: 0;
      background: #fff !important;
      font-size: 12px;
      line-height: 1.25;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .thermal-slip {
      width: 58mm;
      max-width: 58mm;
      padding: 3mm 4mm 10mm 4mm;
      margin: 0 auto;
    }
    .thermal-slip h4 {
      font-size: 19px;
      font-weight: 900 !important;
      text-align: center;
      margin: 0 0 2px 0;
      letter-spacing: 1px;
      text-transform: uppercase;
    }
    .thermal-slip p.subt {
      font-size: 12px;
      font-weight: 800 !important;
      text-align: center;
      margin: 0 0 6px 0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .thermal-slip .t-div {
      border-top: 2px dashed #000 !important;
      margin: 8px 0 !important;
    }
    .thermal-slip .t-num-label {
      text-align: center;
      font-size: 11px;
      font-weight: 700 !important;
      margin-bottom: 2px;
    }
    .thermal-slip .t-num {
      text-align: center;
      font-size: 26px;
      font-weight: 900 !important;
      letter-spacing: 1.5px;
      margin-bottom: 6px;
    }
    .thermal-slip .t-row {
      display: flex;
      justify-content: space-between;
      font-size: 12px;
      font-weight: 700 !important;
      margin: 3px 0;
    }
    .thermal-slip .t-row b {
      font-weight: 900 !important;
      font-size: 12.5px;
    }
    .thermal-slip .t-qr {
      display: flex;
      justify-content: center;
      margin: 10px 0;
    }
    .thermal-slip .t-qr img,
    .thermal-slip .t-qr canvas {
      width: 115px !important;
      height: 115px !important;
      display: block;
      margin: 0 auto;
      image-rendering: pixelated;
    }
    .thermal-slip p.foot {
      font-size: 10.5px;
      font-weight: 700 !important;
      text-align: center;
      margin-top: 8px;
      line-height: 1.3;
    }
  </style>
</head>
<body>
  <div class="thermal-slip">
    ${clone.innerHTML}
  </div>
</body>
</html>`);
    frameDoc.close();

    // Trigger print in iframe after render so XP-58 prints physically
    setTimeout(() => {
      try {
        printFrame.contentWindow.focus();
        printFrame.contentWindow.print();
      } catch (err) {
        console.error('Print iframe error', err);
        window.print();
      }
    }, 250);
  };

  // 7. QR Code Generation for tickets & receipts (sized for 58mm thermal paper)
  function renderBaQr(el) {
    const text = el.dataset.qrcode;
    if (!text) return;

    // A. Use local qr.min.js generator (zero-latency, 100% offline)
    if (typeof qrcode !== 'undefined') {
      try {
        const q = qrcode(0, 'M');
        q.addData(text);
        q.make();
        el.innerHTML = q.createImgTag(3, 2);
        const img = el.querySelector('img');
        if (img) {
          img.style.display = 'block';
          img.style.margin = '6px auto';
          img.style.width = '115px';
          img.style.height = '115px';
          img.style.imageRendering = 'pixelated';
        }
        return;
      } catch (e) {
        console.warn('Local qrcode render failed:', e);
      }
    }

    // B. Check for QRCode class if available
    if (typeof QRCode !== 'undefined') {
      try {
        el.innerHTML = '';
        new QRCode(el, {
          text: text,
          width: 115,
          height: 115,
          colorDark: "#000000",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.M
        });
        return;
      } catch (e) {}
    }

    // C. Network API fallback (qrserver.com)
    const img = document.createElement('img');
    img.src = `https://api.qrserver.com/v1/create-qr-code/?size=115x115&margin=1&data=${encodeURIComponent(text)}`;
    img.alt = `QR code for ${text}`;
    img.style.display = 'block';
    img.style.margin = '6px auto';
    img.style.width = '115px';
    img.style.height = '115px';
    el.innerHTML = '';
    el.appendChild(img);
  }

  document.querySelectorAll('[data-qrcode]').forEach(el => renderBaQr(el));
  window.renderBaQr = renderBaQr;

  // 8. Cash Tender Quick Calculation (BA-44, BA-45)
  const cashInput = document.getElementById('cash-received-input');
  const hiddenCashInput = document.getElementById('hidden-cash-received');
  const dueAmountEl = document.getElementById('due-amount-val');
  const changeValEl = document.getElementById('change-val');
  const confirmPayBtn = document.getElementById('confirm-pay-btn');
  const cashErrorEl = document.getElementById('cash-error-msg');

  if (cashInput && dueAmountEl) {
    function formatCashString(val) {
      let clean = String(val).replace(/[^0-9.]/g, '');
      const parts = clean.split('.');
      let intPart = parts[0].slice(0, 7);
      if (intPart.length > 1 && intPart.startsWith('0')) {
        intPart = intPart.replace(/^0+/, '') || '0';
      }
      const formattedInt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      if (parts.length > 1) {
        return formattedInt + '.' + parts.slice(1).join('').slice(0, 2);
      }
      return formattedInt;
    }

    function getRawAmount(val) {
      const clean = String(val).replace(/,/g, '').trim();
      const num = parseFloat(clean);
      return isNaN(num) ? 0 : num;
    }

    const getDueAmount = () => parseFloat(dueAmountEl.dataset.due || '0');

    const updateChange = (rawCash) => {
      const dueAmount = getDueAmount();
      if (hiddenCashInput) {
        hiddenCashInput.value = rawCash.toFixed(2);
      }
      if (rawCash > 9999999.99) {
        if (changeValEl) changeValEl.textContent = '-';
        if (confirmPayBtn) confirmPayBtn.disabled = true;
        if (cashErrorEl) {
          cashErrorEl.hidden = false;
          cashErrorEl.textContent = 'Cash received cannot exceed \u20B19,999,999.99.';
        }
      } else if (rawCash >= dueAmount) {
        const change = Math.round((rawCash - dueAmount) * 100) / 100;
        if (changeValEl) changeValEl.textContent = `\u20B1${change.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        if (confirmPayBtn) confirmPayBtn.disabled = false;
        if (cashErrorEl) cashErrorEl.hidden = true;
      } else {
        const short = Math.round((dueAmount - rawCash) * 100) / 100;
        if (changeValEl) changeValEl.textContent = '-';
        if (confirmPayBtn) confirmPayBtn.disabled = true;
        if (cashErrorEl) {
          cashErrorEl.hidden = false;
          cashErrorEl.textContent = `Cash received is short by \u20B1${short.toFixed(2)}.`;
        }
      }
    };

    let selectOnMouseUp = false;
    cashInput.addEventListener('focus', () => {
      selectOnMouseUp = true;
      cashInput.select();
    });
    cashInput.addEventListener('mouseup', (e) => {
      if (selectOnMouseUp) {
        selectOnMouseUp = false;
        cashInput.select();
        e.preventDefault();
      }
    });

    cashInput.addEventListener('keydown', (e) => {
      if (
        ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Tab', 'Enter'].includes(e.key) ||
        e.ctrlKey || e.metaKey || e.altKey
      ) {
        if (e.key === 'Backspace' && cashInput.selectionStart === cashInput.selectionEnd) {
          const pos = cashInput.selectionStart;
          if (pos > 0 && cashInput.value[pos - 1] === ',') {
            cashInput.setSelectionRange(pos - 1, pos - 1);
          }
        }
        return;
      }

      if (!/^[0-9.]$/.test(e.key)) {
        e.preventDefault();
        return;
      }

      const val = cashInput.value;
      const selStart = cashInput.selectionStart ?? val.length;
      const selEnd = cashInput.selectionEnd ?? val.length;
      const dotIdx = val.indexOf('.');

      if (e.key === '.') {
        if (dotIdx !== -1 && !val.slice(selStart, selEnd).includes('.')) {
          e.preventDefault();
        }
        return;
      }

      const isTypingInteger = dotIdx === -1 || selStart <= dotIdx;
      if (isTypingInteger) {
        const intDigits = (dotIdx === -1 ? val : val.slice(0, dotIdx)).replace(/[^0-9]/g, '');
        const selectedIntDigits = val.slice(selStart, Math.min(selEnd, dotIdx === -1 ? val.length : dotIdx)).replace(/[^0-9]/g, '');
        if (intDigits.length - selectedIntDigits.length >= 7) {
          e.preventDefault();
          return;
        }
      }

      if (dotIdx !== -1 && selStart > dotIdx) {
        const decDigits = val.slice(dotIdx + 1).replace(/[^0-9]/g, '');
        const selectedDecDigits = val.slice(Math.max(selStart, dotIdx + 1), selEnd).replace(/[^0-9]/g, '');
        if (decDigits.length - selectedDecDigits.length >= 2) {
          e.preventDefault();
          return;
        }
      }
    });

    cashInput.addEventListener('paste', (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text') || '';
      const clean = pasted.replace(/[^0-9.]/g, '');
      const parts = clean.split('.');
      const intPart = parts[0].slice(0, 7);
      const decPart = parts.length > 1 ? '.' + parts.slice(1).join('').slice(0, 2) : '';
      const formatted = formatCashString(intPart + decPart);
      cashInput.value = formatted;
      cashInput.setSelectionRange(formatted.length, formatted.length);
      const raw = getRawAmount(formatted);
      updateChange(raw);
    });

    cashInput.addEventListener('input', (e) => {
      const input = e.target;
      const originalVal = input.value;
      const cursorPos = input.selectionStart ?? originalVal.length;
      const digitsBefore = originalVal.slice(0, cursorPos).replace(/[^0-9.]/g, '').length;

      const formatted = formatCashString(originalVal);
      input.value = formatted;

      let newCursor = 0;
      let digitsCounted = 0;
      while (newCursor < formatted.length && digitsCounted < digitsBefore) {
        if (/[0-9.]/.test(formatted[newCursor])) {
          digitsCounted++;
        }
        newCursor++;
      }
      input.setSelectionRange(newCursor, newCursor);

      const raw = getRawAmount(formatted);
      updateChange(raw);
    });

    cashInput.addEventListener('blur', () => {
      const raw = getRawAmount(cashInput.value);
      if (raw > 0) {
        cashInput.value = raw.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
      } else {
        cashInput.value = '0.00';
      }
      updateChange(raw);
    });

    document.querySelectorAll('[data-quick-cash]').forEach(btn => {
      btn.addEventListener('click', () => {
        const dueAmount = getDueAmount();
        const amt = btn.dataset.quickCash === 'exact' ? dueAmount : parseFloat(btn.dataset.quickCash);
        cashInput.value = amt.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        updateChange(amt);
      });
    });

    const payForm = cashInput.closest('form');
    if (payForm) {
      payForm.addEventListener('submit', (e) => {
        const raw = getRawAmount(cashInput.value);
        const due = getDueAmount();
        if (raw < due) {
          e.preventDefault();
          if (cashErrorEl) {
            cashErrorEl.textContent = `Cash received is short by \u20B1${(due - raw).toFixed(2)}.`;
            cashErrorEl.hidden = false;
          }
          cashInput.focus();
          return;
        }
        if (raw > 9999999.99) {
          e.preventDefault();
          if (cashErrorEl) {
            cashErrorEl.textContent = 'Cash received cannot exceed \u20B19,999,999.99.';
            cashErrorEl.hidden = false;
          }
          cashInput.focus();
          return;
        }
        if (hiddenCashInput) {
          hiddenCashInput.value = raw.toFixed(2);
        }
      });
    }

    // Trigger initial calculation
    const initRaw = getRawAmount(cashInput.value);
    if (initRaw > 0) {
      cashInput.value = initRaw.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
      updateChange(initRaw);
    }
  }
});
