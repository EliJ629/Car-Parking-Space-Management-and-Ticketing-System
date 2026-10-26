{{-- The "Are all these details correct?" screen. details-confirm.js fills the list from the form and shows it before the form is sent.
     $note: one line under the heading. --}}
<dialog class="dcdlg" id="details-confirm" aria-labelledby="details-confirm-t">
  <div class="in">
    <h2 id="details-confirm-t">Are all these details correct?</h2>
    <p class="dcnote">{{ $note ?? 'Check every line before you continue.' }}</p>
    <dl class="dclist" data-confirm-list></dl>
    <div class="actions">
      <button class="btn" type="button" data-confirm-yes>Yes, all correct</button>
      <button class="btn ghost" type="button" data-confirm-no>Go back and check</button>
    </div>
  </div>
</dialog>
<style>
  dialog.dcdlg{border:0;padding:0;border-radius:14px;width:min(520px,calc(100vw - 24px));max-height:calc(100vh - 24px);overflow:auto;background:var(--surface,#fff);color:var(--ink,#16202a);box-shadow:0 20px 60px rgba(0,0,0,.4)}
  dialog.dcdlg::backdrop{background:rgba(10,14,18,.62)}
  dialog.dcdlg .in{padding:20px 20px calc(20px + env(safe-area-inset-bottom,0px));border-top:5px solid var(--paint,#f2b705);border-radius:14px}
  dialog.dcdlg h2{font-size:26px;line-height:1.1;margin:0 0 8px;color:var(--ink,#16202a)}
  dialog.dcdlg .dcnote{margin:0 0 12px;font-size:14px;line-height:1.45;color:var(--muted,#55616d)}
  dialog.dcdlg .dclist{margin:0 0 6px;border:1px solid var(--line,#d9dee3);border-radius:10px;overflow:hidden}
  dialog.dcdlg .dclist div{display:flex;gap:12px;justify-content:space-between;padding:9px 12px;border-bottom:1px solid var(--line,#d9dee3);font-size:14px}
  dialog.dcdlg .dclist div:last-child{border-bottom:0}
  dialog.dcdlg .dclist dt{color:var(--muted,#55616d);flex:none}
  dialog.dcdlg .dclist dd{margin:0;font-weight:700;text-align:right;overflow-wrap:anywhere}
  dialog.dcdlg .dclist dd.plate-line{font-family:var(--f-d,inherit);letter-spacing:.04em}
  dialog.dcdlg .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
  dialog.dcdlg .actions .btn{flex:1 1 180px;min-height:44px}
</style>
