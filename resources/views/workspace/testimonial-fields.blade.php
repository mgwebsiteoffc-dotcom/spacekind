<label class="field"><span>Approved display name</span><input name="attribution" required maxlength="100" value="{{ $entry?->attribution }}"></label>
<label class="field"><span>Client quote</span><textarea name="quote" required maxlength="1000" rows="4">{{ $entry?->quote }}</textarea></label>
<label class="consent"><input type="checkbox" name="permission" value="1" required><span>I have permission to share this wording and attribution.</span></label>
<label class="consent"><input type="checkbox" name="is_visible" value="1" @checked($entry?->is_visible)><span>Show on the approved public profile</span></label>
<label class="consent"><input type="checkbox" name="show_on_home" value="1" @checked($entry?->show_on_home)><span>The client also permits this public quote to appear on the Spacekind homepage.</span></label>
