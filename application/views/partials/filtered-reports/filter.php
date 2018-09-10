<?php
/**
 * @var array $filter
 * @var \Model\Record\Form $form
 * @var Controller\Base $this
 */
?>
<?php 
    $filterrow = function($form, $ord, $filter) {
        ob_start();
        $filteringFields = [];
        foreach ($form->json['children'] as $q) {
            switch ($q['type']) {
                case 'select all that apply':
                case 'select one':
                case 'integer':
                case 'text':
                case 'date':
                    $filteringFields[] = $q;
                    break;
                case 'time':
                    break;
                case 'group':
                    if (!$q['control']['bodyless']) {
                        foreach ($q['children'] as $subq) {
                            if (in_array($subq['type'], ['select all that apply', 'select one', 'integer', 'text', 'date'])) {
                                $subq['name'] = $q['name'] . '/' . $subq['name'];
                                $subq['label'] = $q['label'] . '/' . $subq['label'];
                                $filteringFields[] = $subq;
                            }
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        echo '<div class="filter-row">';
        
        echo '<span class="remove-question"><i class="fa fa-ban"></i></span>';
        
        echo '<span class="field-name">';
        echo '<select name="filter[', $ord, '][name]" class="question">';
        echo '<option value="">Choose question</option>';
        $ftype = $filter['name'] == 'њYњ' ? 'њYњ' : null;
        echo '<option value="њYњ"', $ftype == 'њYњ' ? ' selected' : '', '>Find anywhere</option>';
        foreach ($filteringFields as $filteringField) {
            $isSelected = !strcmp($filter['name'], $filteringField['name']);
            if ($isSelected) {
                $ftype = $filteringField['type'];
            }
            echo '<option value="', $filteringField['name'], '"',  $isSelected ? ' selected="selected"' : '', '>', 
                $filteringField['label'], 
            '</option>';
        }
        echo '</select>';
        echo '</span>';

        $visible = in_array($ftype, ['text', 'integer', 'њYњ']);
        echo '<span class="field-value field-value-text ', $visible ? '' : 'hidden-f', '">',
            '<input name="filter[', $ord, '][value]" type="text" value="', $visible ? htmlspecialchars($filter['value']) : '', '" placeholder="containing value..."', $visible ? '' : ' disabled', '>',
        '</span>';
        
        $visible = in_array($ftype, ['select all that apply', 'select one']);
        echo '<span class="field-value field-value-select ', $visible ? '' : 'hidden-f', '">';
        echo '<select name="filter[', $ord, '][value]"', $visible ? '' : ' disabled', '>';
        echo '<option value="">Choose selected option</option>';
        foreach ($filteringFields as $filteringField) {
            if ($visible && !strcmp($filter['name'], $filteringField['name'])) {
                foreach ($filteringField['children'] as $opt) {
                    echo '<option value="', $opt['name'], '"', !strcmp($filter['value'], $opt['name']) ? ' selected' : '', '>',  $opt['label'], '</option>';
                }
            }
        }
        echo '</select>';
        echo '</span>';
        
        $visible = in_array($ftype, ['date']);
        echo '<span class="field-value field-value-date ', $visible ? '' : 'hidden-f', '">',
            '<input class="datepicker" name="filter[', $ord, '][value][from]" type="text" value="', $visible ? htmlspecialchars($filter['value']['from']) : '', '" placeholder="from"', $visible ? '' : ' disabled', '>',
            '<input class="datepicker" name="filter[', $ord, '][value][to]" type="text" value="', $visible ? htmlspecialchars($filter['value']['to']) : '', '" placeholder="to"', $visible ? '' : ' disabled', '>',
        '</span>';
        
        echo '</div>';
        
        return ob_get_clean();
    };
?>

<form method="get" id="filter-form">
    <h4>Data filtering criteria</h4>
    <div id="filter-criteria">
        <?php $ord = -1; ?>
        <?php foreach ($filter as $row): ?>
            <?php echo $filterrow($form, ++$ord, $row); ?>
        <?php endforeach; ?>
        <?php if (!$filter): ?>
            <?php echo $filterrow($form, $ord + 1, []); ?>
        <?php endif; ?>
    </div>
    <div>
        <a href="#" class="add-criterion">Add filter criterion</a>
    </div>
    <div style="padding: 5px 0;">
        <input type="submit" value="Apply filters">
    </div>
</form>

<?php $this->placeholder('jsReady')->captureStart('append'); ?>
<script>
    var formOpts = <?php echo json_encode($form->json); ?>,
        changeQ = function() {
            var $row = $(this).closest('.filter-row'),
                field = $(this).val(),
                ftype = null;
            if (field == 'њYњ') {
                ftype = 'њYњ';
            }
            $.each(formOpts.children, function() {
                if (this.name == field) {
                    ftype = this.type;
                } else if (this.type == 'group') {
                    var q = this;
                    $.each(q.children, function() {
                        if ((q.name + '/' + this.name) == field) {
                            ftype = this.type;
                        }
                    });
                }
            });
            $row.find('.field-value').hide().find(':input').prop('disabled', true);
            
            switch (ftype) {
                case 'select all that apply':
                case 'select one':
                    var sel = $row.find('.field-value-select').show().find('select').prop('disabled', false);
                    sel.find('option').remove();
                    sel.append('<option value="">Choose selected option</option>');
                    $.each(formOpts.children, function() {
                        if (this.name == field) {
                            $.each(this.children, function() {
                                sel.append('<option value="' + this.name + '">' + this.label + '</option>');
                            });
                        } else if (this.type == 'group') {
                            var q = this;
                            $.each(q.children, function() {
                                if ((q.name + '/' + this.name) == field) {
                                    $.each(this.children, function() {
                                        sel.append('<option value="' + this.name + '">' + this.label + '</option>');
                                    });
                                }
                            });
                        }
                    });
                    break;
                case 'integer':
                case 'text':
                case 'њYњ':
                    $row.find('.field-value-text').show().find(':input').prop('disabled', false);
                    break;
                case 'date':
                    $row.find('.field-value-date').show().find(':input').prop('disabled', false);
                    break;
            }
        };
    
    $('.filter-row .question').on('change', changeQ);
    $('#filter-criteria').on('click', '.remove-question', function() {
        $(this).closest('.filter-row').remove();
    }).find('.datepicker').datepicker({
        format: "yyyy-mm-dd",
        todayHighlight: true
    });
    
    var addQRow = $('#filter-criteria .filter-row:eq(0)').clone(),
        maxRowNum = $('#filter-criteria .filter-row').length;
    $('.add-criterion').on('click', function() {
        var row = addQRow.clone();
        row.find('.question').attr('name', 'filter[' + maxRowNum + '][name]').get(0).selectedIndex = 0;
        row.find('.field-value-text :input').attr('name', 'filter[' + maxRowNum + '][value]').val('');
        row.find('.field-value-select :input').attr('name', 'filter[' + maxRowNum + '][value]');
        row.find('.field-value-date :input').each(function() {
            if ($(this).attr('name').match(/from/)) {
                $(this).attr('name', 'filter[' + maxRowNum + '][value][from]').val('');
            } else {
                $(this).attr('name', 'filter[' + maxRowNum + '][value][to]').val('');
            }
        });
        $('#filter-criteria').append($('<div class="filter-row" />').append(row));
        row.find('.datepicker').datepicker({
            format: "yyyy-mm-dd",
            todayHighlight: true
        });
        $('#filter-criteria').find('.filter-row:last').find('.question').on('change', changeQ).trigger('change');
        maxRowNum++;
        return false;
    });
</script>
<?php $this->placeholder('jsReady')->captureEnd(); ?>