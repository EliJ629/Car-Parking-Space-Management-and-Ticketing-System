{{-- "Still in registration" checkbox, shown under the plate box. $checked: whether it starts ticked.
     Ticked: the plate box stays open and is optional. A temporary plate can be entered; otherwise the plate is added after the LTO issues it. --}}
@php $isTicked = count(old()) ? (bool) old('no_plate') : (bool) ($checked ?? false); @endphp
<label class="no-plate" for="no_plate" style="display:flex;gap:10px;align-items:flex-start;margin:-2px 0 14px;font-size:14px;line-height:1.4;cursor:pointer">
  <input id="no_plate" name="no_plate" type="checkbox" value="1" style="margin-top:3px;flex:none" @checked($isTicked)>
  <span>My vehicle is still in registration (no permanent plate number yet)
    <small class="fine" style="display:block">Leave the plate box empty, or enter the temporary plate you were given. You can replace it with the permanent plate once, after the LTO issues it. You cannot reserve a space until a plate is saved.</small></span>
</label>
