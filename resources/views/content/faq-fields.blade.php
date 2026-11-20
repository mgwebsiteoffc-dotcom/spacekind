<fieldset class="faq-fields" data-faq-row><legend>Question and answer</legend>
<label class="field"><span>Question</span><input name="faqs[{{ $index }}][question]" data-faq-key="question" maxlength="240" value="{{ $faq['question'] ?? '' }}"></label>
<label class="field"><span>Answer</span><textarea name="faqs[{{ $index }}][answer]" data-faq-key="answer" rows="3" maxlength="2000">{{ $faq['answer'] ?? '' }}</textarea></label>
<button type="button" class="text-link" data-remove-faq hidden>− Remove FAQ</button></fieldset>
