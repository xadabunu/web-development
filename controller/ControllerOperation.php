<?php

require_once "controller/MyController.php";
require_once "model/User.php";
require_once "model/Template.php";
require_once "controller/ControllerTemplates.php";

class ControllerOperation extends MyController
{
// --------------------------- Index + Details Operations ------------------------------------ 

    public function index(): void
    {
    }

    public function details(): void
    {
        if (isset($_GET['param1']) && is_numeric($_GET['param1'])) {
            $user = $this->get_user_or_redirect();
            if (!in_array($_GET['param1'], Operation::get_all_operations_id()))
                $this->redirect();
            $op = Operation::get_operation_by_id($_GET['param1']);
            if (!$op->tricount->has_access($user))
                $this->redirect();
            $list = $op->get_participants();
            $amounts = [];
            foreach ($list as $participant) {
                $amounts[$participant->id] = $op->get_user_amount($participant->id);
            }
            $prev = $op->get_previous();
            $next = $op->get_next();
            (new View("operation"))->show([
                "user" => $user,
                "operation" => $op,
                "list" => $list,
                "next" => $next,
                "previous" => $prev,
                "amounts" => $amounts
            ]);
        } else {
            Tools::abort("Invalid or missing argument");
        }
    }


// --------------------------- Add/edit Operations ------------------------------------


    public function add_operation(): void
    {
        if (isset($_GET['param1']) && is_numeric($_GET['param1'])) {
            $user = $this->get_user_or_redirect();
            if (!in_array($_GET['param1'], Tricount::get_all_tricounts_id()))
                $this->redirect();
            $tricount = Tricount::get_tricount_by_id($_GET['param1']);
            if (!$tricount->has_access($user))
                $this->redirect();
            $operation = '';
            $subscriptors = $tricount->get_subscriptors_with_creator();
            $templates = Template::get_templates($tricount->id);
            $errors = [];
            $title = [];
            $amount = [];
            $operation_date = [];
            $initiator = [];
            $templateChoosen = [];
            $userChecked = [];
            $userWeight = [];
            $templateUserWeightList = '';
            $list = '';

            foreach($subscriptors as $subscriptor){
                $userChecked[$subscriptor->id] = 'unchecked';
                $userWeight[$subscriptor->id] = '1';
            }

            if (isset($_POST['title']) && isset($_POST['amount']) && isset($_POST['operation_date']) && isset($_POST['paid_by'])) {
                $title = Tools::sanitize($_POST['title']);
                $amount = floatval(Tools::sanitize($_POST['amount']));
                if ($amount <= 0){
                    $errors ['amount'] = 'Amount must be strictly positive' ;
                }
                $operation_date = $_POST['operation_date'];
                $created_at = Date("Y-m-d H:i:s");
                if(is_numeric($_POST['paid_by'])){
                    $initiator = User::get_user_by_id(Tools::sanitize($_POST['paid_by']));
                }
                $list = self::get_weight($_POST, $tricount);
                $errors = array_merge($errors, self::is_valid_fields($_POST, $tricount));

                if (isset($_POST['templates']) && is_numeric($_POST['templates'])) {
                    $templateChoosen = Template::get_template_by_template_id(Tools::sanitize($_POST['templates']));
                    $templateUserWeightList = $templateChoosen->get_repartition_items();
                    $userChecked = [];
                    $userWeight = [];

                    foreach($subscriptors as $subscriptor){
                        array_key_exists($subscriptor->id, $templateUserWeightList) ? $userChecked[$subscriptor->id] = 'checked' : $userChecked[$subscriptor->id] = 'unchecked';
                        array_key_exists($subscriptor->id, $templateUserWeightList) ? $userWeight[$subscriptor->id] = $templateUserWeightList[$subscriptor->id] : $userWeight[$subscriptor->id] = '1';
                    }
                }

                if (count($errors) == 0) {
                    $operation = new Operation($title, $tricount, $amount, $operation_date, $initiator, $created_at);
                    $errors = $operation->validate_operations();

                    if (isset($_POST["save_template_checkbox"])) {
                        $template = new Template(Tools::sanitize($_POST["template_title"]), $tricount);
                        $errors = array_merge($errors, $template->validate_template());
                    }

                    if (count($errors) == 0) {
                        if (isset($_POST["save_template_checkbox"])) {
                            $template->add_template_from_operation($list, $template);
                        }

                        $operation->persist_operation();
                        $operation->persist_repartition($operation, $list);
                        $this->redirect('tricount', 'operations', $tricount->id);
                    }
                }
            }
            (new View("add_operation"))->show([
                'tricount' => $tricount, 'operation' => $operation, 'subscriptors' => $subscriptors,
                'templates' => $templates, 'errors' => $errors, 'title' => $title, 'amount' => $amount,
                'operation_date' => $operation_date, 'initiator' => $initiator, 'list'=>$list,
                'templateChoosen' => $templateChoosen, 'templateUserWeightList' => $templateUserWeightList, 
                'userChecked' => $userChecked, 'userWeight' => $userWeight
            ]);
        } else
            Tools::abort("Invalid or missing argument.");
    }

    private function is_valid_fields(array $array, Tricount $tricount): array
    {
        $errors = [];
        if (empty($array['title'])) {
            $errors['empty_title'] = "Title is required";
        }
        if (empty($array['amount'])) {
            $errors['empty_amount'] = "Amount is required";
        }
        if (empty($array['paid_by'])) {
            $errors['empty_initiator'] = "You must choose an initiator";
        }
        if (empty($array['operation_date'])) {
            $errors['empty_date'] = "Date of your operation is required";
        }
       
        $listUser = self::get_whom($array, $tricount);

        if(count($listUser) == 0 ){
            $errors['whom'] = "You must choose at least one person";
        }

        $id = [];
        foreach($listUser as $user){
            $id[] = $user->id;
        }
        
        foreach($array as $key => $item){ 
            if(substr($key, 0, 6) == "weight"){
                if(in_array(substr($key, 7), $id)){
                    if(!is_numeric($item) || intval($item) < 1){
                        $errors['weight'] = "Weight must be a strictly positive numeric value";
                    }
                }
            }
        }

        if (isset($array["save_template_checkbox"]) && empty($array["template_title"])) {
            $errors['empty_template_title'] = "Template title is required";
        }

        return $errors;
    }

    public function edit_operation(): void
    {
        $subscriptors = [];
        $userChecked = [];
        $userWeight = [];
        $templates = '';
        $operation = '';
        $errors = [];
        $list = [];
        $title= [];
        $amount = [];
        $operation_date = [];
        $paid_by = [];
        $templateChoosen = [];
        $templateUserWeightList = '';

        if (isset($_GET['param1']) && is_numeric($_GET['param1'])) {
            if (!in_array($_GET['param1'], Operation::get_all_operations_id()))
                $this->redirect();
            $operation = Operation::get_operation_by_id($_GET['param1']);
            $title = $operation->title;
            $user = $this->get_user_or_redirect();
            $operation = Operation::get_operation_by_id($_GET['param1']);
            if (!$operation->tricount->has_access($user))
                $this->redirect();
            $tricount = $operation->tricount;
            $subscriptors = $tricount->get_subscriptors_with_creator();
            $templates = Template::get_templates($tricount->id);
            $list = $operation->get_repartitions();


            foreach($subscriptors as $subscriptor){
                $userChecked[$subscriptor->id] = 'checked';

                array_key_exists($subscriptor->id, $list) ? $userChecked[$subscriptor->id] = 'checked' : $userChecked[$subscriptor->id] = 'unchecked';
                array_key_exists($subscriptor->id, $list) ? $userWeight[$subscriptor->id] = $list[$subscriptor->id] : $userWeight[$subscriptor->id] = '1';
            }


            if (isset($_POST['title']) && isset($_POST['amount']) && isset($_POST['operation_date'])) {
                $operation->title = Tools::sanitize($_POST['title']);
                $operation->amount = floatval(Tools::sanitize($_POST['amount']));
                $operation->initiator = User::get_user_by_id($_POST['paid_by']);
                $operation->operation_date = $_POST['operation_date'];
                $list = self::get_weight($_POST, $tricount);
                $errors = array_merge($errors, self::is_valid_fields($_POST, $tricount));
                $errors = array_merge($errors, $operation->validate_operations());

                if (isset($_POST['templates']) && is_numeric($_POST['templates'])) {
                    $userChecked = [];
                    $userWeight = [];
                    $templateChoosen = Template::get_template_by_template_id(Tools::sanitize($_POST['templates']));
                    $templateUserWeightList = $templateChoosen->get_repartition_items();
                    $templateUsers = $templateChoosen->get_template_users();

                    foreach($subscriptors as $subscriptor){
                        array_key_exists($subscriptor->id, $templateUsers) ? $userChecked[$subscriptor->id] = 'checked' : $userChecked[$subscriptor->id] = 'unchecked';
                        array_key_exists($subscriptor->id, $templateUserWeightList) ? $userWeight[$subscriptor->id] = $templateUserWeightList[$subscriptor->id] : $userWeight[$subscriptor->id] = '1';
                    }
                }

                if (isset($_POST["save_template_checkbox"])) {
                    $template = new Template(Tools::sanitize($_POST["template_title"]), $tricount);
                    $errors = array_merge($errors, $template->validate_template());
                }

                if (count($errors) == 0) {
                    if (isset($_POST["save_template_checkbox"])) {
                        $template->add_template_from_operation($list, $template);
                    }

                    $operation->persist_repartition($operation, $list);
                    $operation->persist_operation();
                    $this->redirect('tricount', 'operations', $tricount->id);
                }
            }
        (new View('edit_operation'))->show([
            'operation' => $operation, 'errors' => $errors,
            'subscriptors' => $subscriptors, 'templates' => $templates, 'list' => $list,
            'titleValue' => $title, 'amountValue' => $amount, 'operation_dateValue' => $operation_date, 'paid_byValue' => $paid_by,
            'templateChoosen' => $templateChoosen, 'templateUserWeightList' => $templateUserWeightList, 'userChecked' => $userChecked,
            'userWeight' => $userWeight
        ]);
        }else{
            Tools::abort("Invalid or missing argument");
        }
    }


//---- Fonction private get sur le poids et les users selectionnés lors d'un add ou edit operation

    private function get_whom(array $array, Tricount $tricount): array
    {
        $list = $tricount->get_subscriptors_with_creator();
        $result = [];
        foreach ($list as $sub) {
            if (array_key_exists($sub->id, $array)) {
                $result[] = $sub;
            }
        }
        return $result;
    }

    private function get_weight(array $array, Tricount $tricount): array
    {
        $list = self::get_whom($array, $tricount);
        $result = [];
        foreach ($list as $sub) {
            $result[$sub->id] = $array['weight_' . $sub->id];
        }
        return $result;
    }


// --------------------------- Delete + ConfirmDelete operations ------------------------------------ 


    public function delete_operation(): void
    {
        if (isset($_GET['param1']) && is_numeric($_GET['param1'])){
            $user = $this->get_user_or_redirect();
            if (!in_array($_GET['param1'], Operation::get_all_operations_id()))
                $this->redirect();
            $operation = Operation::get_operation_by_id($_GET['param1']);
            if (!$operation->tricount->has_access($user))
                $this->redirect();
            (new View('delete_operation'))->show(['operation' => $operation]);
        } else {
            Tools::abort('Invalid or missing arguments.');
        }
    }    

    public function confirm_delete_operation(): void
    {
        if (isset($_GET['param1']) && is_numeric($_GET['param1'])){
            if (!in_array($_GET['param1'], Operation::get_all_operations_id()))
                $this->redirect();
            $operation = Operation::get_operation_by_id($_GET['param1']);
            $operation->delete_operation_cascade();
            $this->redirect('tricount', 'operations', $operation->tricount->id);
        } else {
            Tools::abort('Invalid or missing argument.');
        }       

    }

// --------------------------- Apply template for add/edit operation ------------------------------------ 

    public function apply_template_edit_operation(): void
    {
        $subscriptors = [];
        $templates = '';
        $operation = '';
        $errors = [];
        $list = [];
        $title = [];
        $amount = [];
        $operation_date = [];
        $paid_by = [];
        $templateChoosen = [];
        $userChecked = [];
        $userWeight = [];
        $templateUserWeightList = '';

        if (isset($_GET['param1']) && is_numeric($_GET['param1'])) {
            $user = $this->get_user_or_redirect();
            if (!in_array($_GET['param1'], Operation::get_all_operations_id()))
                $this->redirect();
            $operation = Operation::get_operation_by_id($_GET['param1']);
            if (!$operation->tricount->has_access($user))
                $this->redirect();
            $tricount = $operation->tricount;
            $subscriptors = $tricount->get_subscriptors_with_creator();
            $templates = Template::get_templates($tricount->id);
            $list = $operation->get_repartitions();

            foreach($subscriptors as $subscriptor){
                $userChecked[$subscriptor->id] = 'unchecked';
                $userWeight[$subscriptor->id] = '1';
            }

            if (isset($_POST['title'])) {
                $title = Tools::sanitize($_POST['title']);
            }
            if (isset($_POST['amount'])) {
                $amount = Tools::sanitize($_POST['amount']);
            }
            if (isset($_POST['operation_date'])) {
                $operation_date = $_POST['operation_date'];
            }
            if (isset($_POST['paid_by'])) {
                $paid_by = User::get_user_by_id(Tools::sanitize($_POST['paid_by']));
            }
            if (isset($_POST['templates']) && is_numeric($_POST['templates']) ) {
                $templateChoosen = Template::get_template_by_template_id(Tools::sanitize($_POST['templates']));
                $templateUserWeightList = $templateChoosen->get_repartition_items();
                $userChecked = [];
                $userWeight = [];

                foreach($subscriptors as $subscriptor){
                    array_key_exists($subscriptor->id, $templateUserWeightList) ? $userChecked[$subscriptor->id] = 'checked' : $userChecked[$subscriptor->id] = 'unchecked';
                    array_key_exists($subscriptor->id, $templateUserWeightList) ? $userWeight[$subscriptor->id] = $templateUserWeightList[$subscriptor->id] : $userWeight[$subscriptor->id] = '1';
                }

            } 

            (new View('edit_operation'))->show([
                'operation' => $operation, 'errors' => $errors,
                'subscriptors' => $subscriptors, 'templates' => $templates, 'list' => $list,
                'titleValue' => $title, 'amountValue' => $amount, 'operation_dateValue' => $operation_date, 'paid_byValue' => $paid_by,
                'templateChoosen' => $templateChoosen, 'templateUserWeightList' => $templateUserWeightList, 'userChecked' => $userChecked,
                'userWeight' => $userWeight
            ]);
        } else
            Tools::abort("Invalid or missing arument.");
    }

    public function apply_template_add_operation(): void
    {
        if (isset($_GET['param1']) && is_numeric($_GET['param1'])) {
            $user = $this->get_user_or_redirect();
            if (!in_array($_GET['param1'], Tricount::get_all_tricounts_id()))
                $this->redirect();
            $tricount = Tricount::get_tricount_by_id($_GET['param1']);
            if (!$tricount->has_access($user))
                $this->redirect();
            $operation = '';
            $subscriptors = $tricount->get_subscriptors_with_creator();
            $templates = Template::get_templates($tricount->id);
            $errors = [];
            $title = [];
            $amount = [];
            $operation_date = [];
            $initiator = [];
            $templateChoosen = [];
            $userChecked = [];
            $userWeight = [];
            $templateUserWeightList = '';
            $list = '';

            foreach($subscriptors as $subscriptor){
                $userChecked[$subscriptor->id] = 'unchecked';
                $userWeight[$subscriptor->id] = '1';
            }

            if (isset($_POST['title'])) {
                $title = Tools::sanitize($_POST['title']);
            }
            if (isset($_POST['amount'])) {
                $amount = Tools::sanitize($_POST['amount']);
            }
            if (isset($_POST['operation_date'])) {
                $operation_date = $_POST['operation_date'];
            }
            if (isset($_POST['paid_by']) && is_numeric($_POST['paid_by'])) {
                $initiator = User::get_user_by_id(Tools::sanitize($_POST['paid_by']));
            }
            if (isset($_POST['templates']) && is_numeric($_POST['templates'])) {
                $templateChoosen = Template::get_template_by_template_id(Tools::sanitize($_POST['templates']));
                $templateUserWeightList = $templateChoosen->get_repartition_items();

                $userChecked = [];
                $userWeight = [];

                foreach($subscriptors as $subscriptor){
                    array_key_exists($subscriptor->id, $templateUserWeightList) ? $userChecked[$subscriptor->id] = 'checked' : $userChecked[$subscriptor->id] = 'unchecked';
                    array_key_exists($subscriptor->id, $templateUserWeightList) ? $userWeight[$subscriptor->id] = $templateUserWeightList[$subscriptor->id] : $userWeight[$subscriptor->id] = '1';
                }
            }
            (new View("add_operation"))->show([
                'tricount' => $tricount, 'operation' => $operation, 'subscriptors' => $subscriptors,
                'templates' => $templates, 'errors' => $errors, 'title' => $title, 'amount' => $amount,
                'operation_date' => $operation_date, 'initiator' => $initiator, 'list'=>$list,
                'templateChoosen' => $templateChoosen, 'templateUserWeightList' => $templateUserWeightList,
                'userChecked' => $userChecked, 'userWeight' => $userWeight
            ]);
        } else
            Tools::abort("Invalid or missing arument.");
    }
}