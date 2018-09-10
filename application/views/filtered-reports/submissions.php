<?php 
/**
 * @var \Model\Record\Form $form
 * @var \Model\Record\Submission[] $submissions
 * @var array $filter
 * @var integer $count
 * @var integer $page
 * @var integer $pp
 * @var string $xformId
 * @var string $username
 * @var Controller\Base $this
 */
?>
<div class="container-fluid container__wide main formpack_html_export">
    <style type="text/css" media="screen">
    
    html, body {
      padding-top: 1px!important;
    }
    
    .main {
      max-width: 100%;
      padding: 30px 20px 0px;
    }
    
    
    #submissions-container {
        margin-top: 10px;
    }
    
    table.submissions {
      border-collapse: collapse;
      overflow: auto;
      display: block;
      font-size: 10px;
      margin-top: 0px;
      margin-bottom: 0px;
    }
    
    
    table.submissions tr:nth-child(even) {
        background-color: #f5f5f5;
    }
    
    
    table.submissions td, 
    table.submissions th {
      border: 1px solid #999;
      min-width: 0px;
      text-align: left;
      padding-left: 3px;
      min-width: 60px;
      padding-right: 3px;
    }
    
    table.submissions th {
      padding-top: 5px;
      font-weight: bold;
      vertical-align: top;
      padding-bottom: 5px;
    }
    
    .cell-content-wrap {
      display: inline-block;
      overflow: hidden;
      max-width: 99px;
      padding: 0px 3px;
      margin: 0px;
      vertical-align:top;
      word-wrap: break-word;
    }
    
    table.submissions td .cell-content-wrap {
      white-space: nowrap;
      text-overflow: ellipsis;
      height: 17px;
    }
    
    table.submissions th .cell-content-wrap {
      max-height: 66px;
      line-height: 13px;
    }
    
    #pagination {
      bottom: 0px;
      margin: 0px;
      padding: 2px 0 0;
      border-top: 1px solid #ddd;
    }
    
    .pagination {
      margin: 0;
      text-align: center;
      font-size: 12px;
      font-weight: bold;
    }
    
    .pagination .disabled {
      color:#ccc;
    }
    
    
    .formpack_html_export__options label, .formpack_html_export__options button, .formpack_html_export__options select, .formpack_html_export__options input {
      font-size: 11px;
      display: inline;
      margin: 0px;
    }
    
    .formpack_html_export__options button {
      font-size:12px;
      margin:0 50px;
    }
    
    
    .formpack_html_export__options select {
        height: 23px;
    }
    
    .formpack_html_export__options label {
        margin: 0 0px 0 25px;
    }
    
    span.select.select--group {
        margin-left: 50px;
    }
    
    .select--group input[type="input"] {
        width: 20px;
    }
    
    .select--group button {
        height: 30px;
    }
    
    button {}
    
    .formpack_html_export__options .select--lang label {
        margin-left: 0px;
    }
    
    h1 {
        font-size: 1em;
    }
    
    .formpack_html_export__options {
        margin-top: 68px;
    }
    
    .icon-align {
        font-size: 36px;
        vertical-align: middle;
    }
    </style>
    
    <div class="sub-header-bar">
      <div>
        <a class="sub-header__back" href="/<?php echo $username; ?>/forms/<?php echo $xformId; ?>"><i class="fa fa-chevron-left"></i> Return to <?php echo $form->title; ?></a>
      </div>
    </div>
    
    <div class="formpack_html_export__options"></div>
    
    <div style="float: right;">
        <a href="<?php echo $this->url(['action' => 'export'], false, true, 'submissions')?>"><i class="k-icon-download icon-align"></i> Download with filters applied</a>
    </div>
    
    <?php echo $this->partial('filtered-reports/filter.php', ['filter' => $filter, 'form' => $form]); ?>
    
<?php 
$getSubmissionValue = function($q, $value) {
    switch ($q['type']):
        case 'select one':
            foreach ($q['children'] as $child):
            if ($child['name'] == $value):
            $value = $child['label'];
            break;
            endif;
            endforeach;
            break;
        case 'select all that apply':
            $keys = explode(' ', $value);
            $value = [];
            foreach ($q['children'] as $child):
            if (in_array($child['name'], $keys)):
            $value[] = $child['label'];
            endif;
            endforeach;
            $value = join(' ', $value);
            break;
        case 'text':
        case 'start':
        case 'end':
        case 'integer':
        case 'calculate':
        case 'today':
        case 'username':
        case 'simserial':
        case 'subscriberid':
        case 'deviceid':
        case 'phonenumber':
        case 'date':
        case 'time':
            break;
        default:
//             var_dump($q);
//             mydie();
            break;
    endswitch;
    
    return $value;
}

?>
    
    <div id="submissions-container">
        <table class="submissions">
            <thead>
                <tr>
                    <th>View or Edit</th>
                    <?php foreach ($form->json['children'] as $q): ?>
                        <?php if ($q['type'] == 'group'): ?>
                            <?php if ($q['control']['bodyless']) continue; ?>
                            <?php foreach ($q['children'] as $subq): ?>
                                <th><span class="cell-content-wrap"><?php echo $q['label'] ? $q['label'] : $q['name']; ?>/<?php echo $subq['label'] ? $subq['label'] : $subq['name']; ?></span></th>
                            <?php endforeach; ?>                 
                        <?php else: ?>
                            <th><span class="cell-content-wrap"><?php echo $q['label'] ? $q['label'] : $q['name']; ?></span></th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <th>_id</th>
                    <th>_uuid</th>
                    <th>_submission_time</th>
                </tr>
            </thead>
        
            <tbody>
                <?php foreach ($submissions as $submission): ?>
                    <tr>
                        <th><a href="/<?php echo $username; ?>/forms/<?php echo $xformId; ?>/instance#/<?php echo $submission->id; ?>">Open</a></th>
                        <?php foreach ($form->json['children'] as $q): ?>
                            <?php if (($q['type'] == 'group') && ($q['control']['bodyless'])) continue; ?>
                            <?php 
                                if ($q['type'] == 'group') {
                                    if ($q['control']['bodyless']) continue;
                                    foreach ($q['children'] as $subq) {
                                        $key = $q['name'] . '/' . $subq['name'];
                                        $value = $getSubmissionValue($subq, $submission['json'][$key]);
                            ?>
                                    <td><span class="cell-content-wrap"><?php echo $value; ?></span></td>
                            <?php
                                    }
                                } else {
                                    $key = $q['name'];
                                    $value = $getSubmissionValue($q, $submission['json'][$key]);
                            ?>
                                    <td><span class="cell-content-wrap"><?php echo $value; ?></span></td>
                            <?php
                                }
                            ?>
                        <?php endforeach; ?>
                        <td><span class="cell-content-wrap"><?php echo $submission->id; ?></span></td>
                        <td><span class="cell-content-wrap"><?php echo $submission->uuid; ?></span></td>
                        <td><span class="cell-content-wrap"><?php echo $submission->date_modified; ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <br clear="all">
    <div id="pagination">
        <div class="row pagination">
            <div class="col-xs-12 col-md-10 col-md-push-1 text-center">
                <?php echo $this->partial('paginator.php', ['count' => $count, 'page' => $page, 'perPage' => $pp]); ?>
            </div>
        </div>    
    </div>
    <br>
</div>
<br clear="all">
<br>

<?php $this->placeholder('jsReady')->captureStart(); ?>
<script>
    $('#submissions-container > table').doubleScroll({
        resetOnWindowResize: true,
        onlyIfScroll: false,
        contentElement: $('#submissions-container > table > tbody')
    });
</script>
<?php $this->placeholder('jsReady')->captureEnd(); ?>
