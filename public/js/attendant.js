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
        document.querySelectorAll('.ba-alerts-list li.unread').forEach(li => li.classList.remove('unread'));
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

  // 6. Thermal Print helper (Optimized for 58mm thermal printer roll)
  window.baPrint = function (selector) {
    const target = selector
      ? document.querySelector(selector)
      : (document.querySelector('dialog[open] .ba-thermal') || document.querySelector('.ba-thermal'));

    if (!target) {
      window.print();
      return;
    }

    // Close preview modal if currently open so user returns to normal state
    const openDialog = target.closest('dialog') || document.querySelector('dialog[open]');
    if (openDialog && typeof openDialog.close === 'function') {
      openDialog.close();
    }

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

    // Trigger print in iframe after render
    setTimeout(() => {
      try {
        printFrame.contentWindow.focus();
        printFrame.contentWindow.print();
      } catch (err) {
        console.error('Print iframe error', err);
        window.print();
      }
    }, 200);
  };

  // 7. QR Code Generation for tickets (sized for 58mm receipt paper)
  document.querySelectorAll('[data-qrcode]').forEach(el => {
    const text = el.dataset.qrcode;
    if (text && typeof QRCode !== 'undefined') {
      new QRCode(el, {
        text: text,
        width: 110,
        height: 110,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
      });
    }
  });

  // 8. Cash Tender Quick Calculation (BA-44, BA-45)
  const cashInput = document.getElementById('cash-received-input');
  const dueAmountEl = document.getElementById('due-amount-val');
  const changeValEl = document.getElementById('change-val');
  const confirmPayBtn = document.getElementById('confirm-pay-btn');
  const cashErrorEl = document.getElementById('cash-error-msg');

  if (cashInput && dueAmountEl) {
    const dueAmount = parseFloat(dueAmountEl.dataset.due || '0');

    const updateChange = (val) => {
      const cash = parseFloat(val || '0');
      if (cash >= dueAmount) {
        const change = (cash - dueAmount).toFixed(2);
        if (changeValEl) changeValEl.textContent = `\u20B1${change}`;
        if (confirmPayBtn) confirmPayBtn.disabled = false;
        if (cashErrorEl) cashErrorEl.hidden = true;
      } else {
        const short = (dueAmount - cash).toFixed(2);
        if (changeValEl) changeValEl.textContent = '-';
        if (confirmPayBtn) confirmPayBtn.disabled = true;
        if (cashErrorEl) {
          cashErrorEl.hidden = false;
          cashErrorEl.textContent = `Cash received is short by \u20B1${short}.`;
        }
      }
    };

    cashInput.addEventListener('input', (e) => updateChange(e.target.value));

    document.querySelectorAll('[data-quick-cash]').forEach(btn => {
      btn.addEventListener('click', () => {
        const amt = btn.dataset.quickCash === 'exact' ? dueAmount : parseFloat(btn.dataset.quickCash);
        cashInput.value = amt.toFixed(2);
        updateChange(amt);
      });
    });
  }
});
