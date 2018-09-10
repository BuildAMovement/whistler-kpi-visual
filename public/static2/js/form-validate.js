var FormValidate = function(form, vhPauseMs) {
    if (typeof vhPauseMs == 'undefined') {
        vhPauseMs = 100;
    }
    
    $(':radio, :checkbox', form).on('click', function(e) {
        if ($(this).is('[readonly]') || $(this).is('[disabled]')) {
            e.stopImmediatePropagation();
            return false;
        }
    });
    
    var chkgroups = $('input.group-required', form),
    grptags = $.unique(chkgroups.map(function() {
        return $(this).data('group');
    }).get());
    chkgroups.on('click.grpreq keyup.grpreq input.grpreq paste.grpreq focus.grpreq', function() {
        var grptag = $(this).data('group'),
            grp = chkgroups.filter('[data-group="' + grptag + '"]'),
            isCheckbox = grp.first().is(':checkbox'),
            chkd = isCheckbox ? grp.filter(':checked').length : grp.filter(function() { return this.value != "" }).length;
        grp.attr('required', !chkd).each(function() { 
            this.setCustomValidity(chkd ? '' : (isCheckbox ? 'Please select at least one checkbox' : 'Please fill at least one field'));
            $(this).data('group-req-error', !chkd).triggerHandler('invalid-custom');
        });
    });
    $.each(grptags, function() {
        chkgroups.filter('[data-group="' + this + '"]').triggerHandler('click.grpreq');
    });
    
    setTimeout(function() {
        // Suppress the default bubbles
        form.addEventListener("invalid", function(event) {
            $(this).data('submitted', true);
            $(this).find(':invalid').filter(':input').eq(0).focus();
            event.preventDefault();
        }, true);
        // .br == bubble replacement
        $(form)
        .find(':input')
            .on('focus.br', function(e) {
                var $this = $(this);
                if ($this.not('.selectable').is('[readonly]') || $this.is('[disabled]')) {
                    e.stopImmediatePropagation();
                    $this.trigger('blur.br');
                    setTimeout(function() {
                        var inputs = $(':input:not(:hidden)', $this.get(0).form); 
                        inputs.eq(inputs.index($this) + 1).focus();
                    }, 1);
                    return false;
                }
                $(form).find('.input-group').removeClass('focus');
                $this.closest('.input-group').addClass('focus');
            })
            .on('blur.br', function(e) {
                var $this = $(this);
                $this.triggerHandler('blur.mask');
                $this.triggerHandler('validate-msg.br');
                setTimeout(function() {
                    $this.closest('.input-group').removeClass('focus');
                }, vhPauseMs);
            })
            .on('invalid.br', function(e) {
                $(this).trigger('validate-msg.br');
                e.preventDefault();
            })
            .on('input.br', function() {
                this.setCustomValidity('');
            })
            .on('input.br keyup.br change.br', function(e) {
                $(this).trigger('validate-msg.br');
            })
            .on('validate-msg.br', function() {
                $(this.form).trigger('validate-msg.br');
            })
        .end()
        .on('validate-msg.br', function() {
            var form = this,
            $form = $(this),
            vmTo = $form.data('vm-to');
            if (vmTo) clearTimeout(vmTo);
            $form.data('vm-to', setTimeout(function() {
                $form.find(':input').each(function() {
                    var igp, igc, field = this, $fld = $(field);
                    if ($fld.data('vm-igp')) {
                        igp = $fld.data('vm-igp');
                        igc = $fld.data('vm-igc');
                    } else {
                        igp = $fld.closest('.input-group');
                        igc = $fld.add(igp);
                    }
                    igc.toggleClass('has-content', !$fld.is('[disabled]') && ($fld.val() != null) && !!$fld.val().length);
                    $fld.triggerHandler('invalid-custom');
                    if ($fld.is(':valid')) {
                        igc.removeClass('marked-invalid');
                        (igp.length ? igp : $fld).nextAll('.clerrors').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        if ($form.data('submitted') && field.validationMessage) {
                            igc.addClass('marked-invalid');
                            (igp.length ? igp : $fld).nextAll('.clerrors').remove().end().after('<ul class="clerrors"><li>' + field.validationMessage + '</li></ul>');
                        }
                    }
                    if (!$fld.data('vm-igp')) {
                        $fld.data('vm-igp', igp).data('vm-igc', igc);
                    }
                });
            }, vhPauseMs));
        })
        .trigger('validate-msg.br')
        .on('submit.br', function(e) {
            $(this).data('submitted', true);
            // Support Safari, iOS Safari, and the Android browser—each of which do not prevent
            // form submissions by default
            if (!this.checkValidity()) {
                event.preventDefault();
            }
        })
        ;
        setTimeout(function(form) { $(form).removeClass('form-init'); }, vhPauseMs);
    }, vhPauseMs);
};

(function($) {
    $('form').each(function() {
        var vhPauseMs = 100;
        FormValidate(this, vhPauseMs);
    });
})($);