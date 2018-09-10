<?php
namespace Controller;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Ufw\Exception\BreakExecution;

class FilteredReports extends Base
{

    const PER_PAGE = 25;

    protected function middlewaresPre()
    {
        return [
            \Middleware\User::class,
            \Middleware\AccessRestriction::class
        ];
    }

    public function accessRestriction()
    {
        parent::accessRestriction();
        
        if (!$this->user->isLoggedOn() || !$this->user->is_active) {
            throw new BreakExecution('', 401);
        }
        
        return $this;
    }

    public function indexAction()
    {}

    public function exportAction()
    {
        $filter = $this->getRequest()->getParam('filter', []);
        
        list ($form, ) = $this->getFormRequestedOr404();
        
        $submissionModel = new \Model\Submission();
        $params = [
            'where' => [],
            'orderby' => 'id ASC'
        ];
        $where = & $params['where'];
        $where['xform_id'] = $form->id;
        
        $where = $this->applyFilters($form, $where, $filter);
        
        $submissions = $submissionModel->read($params['where'], $params['orderby'], $params['limit']);
        
        ini_set('memory_limit', '256M');
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $pColumn = 0;
        $pRow = 1;
        foreach ($form->json['children'] as $q) {
            if ($q['type'] == 'group') {
                if ($q['control']['bodyless']) {
                    continue;
                }
                foreach ($q['children'] as $subq) {
                    $val = ($q['label'] ? $q['label'] : $q['name']) . '/' . ($subq['label'] ? $subq['label'] : $subq['name']);
                    $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, $val);
                }
            } else {
                $val = $q['label'] ? $q['label'] : $q['name'];
                $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, $val);
            }
        }
        $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, '_id');
        $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, '_uuid');
        $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, '_submission_time');
        
        $getSubmissionValue = function ($q, $value) {
            switch ($q['type']) {
                case 'select one':
                    foreach ($q['children'] as $child) :
                        if ($child['name'] == $value) :
                            $value = $child['label'];
                            break;
                    endif;
                        
                    endforeach
                    ;
                    break;
                case 'select all that apply':
                    $keys = explode(' ', $value);
                    $value = [];
                    foreach ($q['children'] as $child) :
                        if (in_array($child['name'], $keys)) :
                            $value[] = $child['label'];
                    endif;
                        
                    endforeach
                    ;
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
                    break;
            }
            return $value;
        };
        
        foreach ($submissions as $submission) {
            $pRow++;
            $pColumn = 0;
            foreach ($form->json['children'] as $q) {
                if ($q['type'] == 'group') {
                    if ($q['control']['bodyless']) {
                        continue;
                    }
                    foreach ($q['children'] as $subq) {
                        $key = $q['name'] . '/' . $subq['name'];
                        $value = $getSubmissionValue($subq, $submission['json'][$key]);
                        $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, $value);
                    }
                } else {
                    $key = $q['name'];
                    $value = $getSubmissionValue($q, $submission['json'][$key]);
                    $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, $value);
                }
            }
            $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, $submission->id);
            $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, $submission->uuid);
            $sheet->setCellValueByColumnAndRow($pColumn++, $pRow, $submission->date_modified);
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $form->title . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        die();
    }

    public function submissionsAction()
    {
        $xformId = $this->getRequest()->getParam('xform_id');
        $username = $this->getRequest()->getParam('username');
        $filter = $this->getRequest()->getParam('filter', []);
        
        list ($form, $isOwner, $canEdit, $canView) = $this->getFormRequestedOr404();
        
        $page = $this->getRequest()->getParam('page', 1);
        $pp = $this->getRequest()->getParam('pp', self::PER_PAGE);
        
        $submissionModel = new \Model\Submission();
        $params = [
            'where' => [],
            'limit' => "$pp OFFSET " . (($page - 1) * $pp),
            'orderby' => 'id ASC'
        ];
        $where = & $params['where'];
        $where['xform_id'] = $form->id;
        
        $where = $this->applyFilters($form, $where, $filter);
        
        $submissions = $submissionModel->read($params['where'], $params['orderby'], $params['limit']);
        
        $this->render([
            'form' => $form,
            'submissions' => $submissions,
            'count' => $submissions->getTotalCount(),
            'page' => $page,
            'pp' => $pp,
            'xformId' => $xformId,
            'username' => $username,
            'filter' => $filter,
            'isOwner' => $isOwner,
            'canEdit' => $canEdit,
            'canView' => $canView
        ]);
    }

    /**
     *
     * @see django.shortcuts.get_object_or_404
     *
     * @throws \Ufw\Exception\BreakExecution
     * @return \Model\Record\Form
     */
    protected function getFormRequestedOr404()
    {
        /*
         * def get_xform_and_perms(username, id_string, request):
         * xform = get_object_or_404(XForm, user__username=username, id_string=id_string)
         * is_owner = xform.user == request.user
         * can_edit = is_owner or request.user.has_perm('logger.change_xform', xform)
         * can_view = can_edit or request.user.has_perm('logger.view_xform', xform)
         * return [xform, is_owner, can_edit, can_view]
         */
        $xformId = $this->getRequest()->getParam('xform_id');
        $username = $this->getRequest()->getParam('username');
        
        $dbc = $this->getDb();
        $query = "
            SELECT f.*
            FROM " . \Model\Record\Form::getTableName() . " f
            INNER JOIN " . \Model\Record\User::getTableName() . " u ON f.user_id = u.id
            WHERE " . $dbc->subquery([
            'f.id_string' => $xformId,
            'u.username' => $username
        ], ' AND ');
        list ($form) = @$dbc->fetch_all($query, \Model\Record\Form::class, 'local');
        
        if (!$form) {
            throw new \Ufw\Exception\BreakExecution('', 404);
        }
        
        $isOwner = $form->user_id == $this->user->id;
        
        $canEdit = $isOwner || $this->user->hasPerm('logger.change_xform', $form);
        $canView = $canEdit || $this->user->hasPerm('logger.view_xform', $form);
        
        if (!$canView) {
            throw new \Ufw\Exception\BreakExecution('', 404);
        }
        
        return [
            $form,
            $isOwner,
            $canEdit,
            $canView
        ];
    }

    protected function applyFilters($form, $where, $filters)
    {
        if (!$filters)
            return $where;
        
        foreach ($filters as $filter) {
            if ((is_scalar($filter['value']) && !strlen($filter['value'])) || !$filter['value']) {
                continue;
            }
            if (!trim($filter['name'])) {
                continue;
            }
            
            if ($filter['name'] == 'њYњ') {
                $where[] = new \Db\ConditionExplicit("EXISTS (SELECT 1 FROM json_each_text(json) WHERE value ILIKE '%" . addslashes($filter['value']) . "%')");
                continue;
            }
            
            foreach ($form->json['children'] as $question) {
                if (!strcmp($filter['name'], $question['name'])) {
                    switch ($question['type']) {
                        case 'select one':
                            $where[] = new \Db\ConditionExplicit("((\"json\"->'{$filter['name']}')::jsonb ? '" . addslashes($filter['value']) . "')");
                            break;
                        case 'select all that apply':
                            $where[] = new \Db\ConditionExplicit("(\"json\"->>'{$filter['name']}' ILIKE '%" . addslashes($filter['value']) . "%')");
                            break;
                        case 'text':
                            $where[] = new \Db\ConditionExplicit("(\"json\"->>'{$filter['name']}' ILIKE '%" . addslashes($filter['value']) . "%')");
                            break;
                        case 'integer':
                            $where[] = new \Db\ConditionExplicit("((\"json\"->'{$filter['name']}')::jsonb ? '" . addslashes($filter['value']) . "')");
                            break;
                        case 'date':
                            if ($filter['value']['from']) {
                                $where[] = new \Db\ConditionExplicit("((\"json\"->>'{$filter['name']}') >= '" . addslashes($filter['value']['from']) . "')");
                            }
                            if ($filter['value']['to']) {
                                $where[] = new \Db\ConditionExplicit("((\"json\"->>'{$filter['name']}') <= '" . addslashes($filter['value']['to']) . "')");
                            }
                            break;
                    }
                    break;
                }
                if (!strcmp($question['type'], 'group')) {
                    foreach ($question['children'] as $subq) {
                        if (!strcmp($filter['name'], $question['name'] . '/' . $subq['name'])) {
                            switch ($subq['type']) {
                                case 'select one':
                                    $where[] = new \Db\ConditionExplicit("((\"json\"->'{$filter['name']}')::jsonb ? '" . addslashes($filter['value']) . "')");
                                    break;
                                case 'select all that apply':
                                    $where[] = new \Db\ConditionExplicit("(\"json\"->>'{$filter['name']}' ILIKE '%" . addslashes($filter['value']) . "%')");
                                    break;
                                case 'text':
                                    $where[] = new \Db\ConditionExplicit("(\"json\"->>'{$filter['name']}' ILIKE '%" . addslashes($filter['value']) . "%')");
                                    break;
                                case 'integer':
                                    $where[] = new \Db\ConditionExplicit("((\"json\"->'{$filter['name']}')::jsonb ? '" . addslashes($filter['value']) . "')");
                                    break;
                                case 'date':
                                    if ($filter['value']['from']) {
                                        $where[] = new \Db\ConditionExplicit("((\"json\"->>'{$filter['name']}') >= '" . addslashes($filter['value']['from']) . "')");
                                    }
                                    if ($filter['value']['to']) {
                                        $where[] = new \Db\ConditionExplicit("((\"json\"->>'{$filter['name']}') <= '" . addslashes($filter['value']['to']) . "')");
                                    }
                                    break;
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        return $where;
    }
}