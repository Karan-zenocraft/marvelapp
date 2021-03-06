<?php

namespace api\controllers;

use common\components\Common;
use common\models\DeviceDetails;
use common\models\EmailFormat;
use common\models\Users;
use Yii;
use common\models\VehicleDetails;
use common\models\VehicleTypes;

/* USE COMMON MODELS */
use yii\web\Controller;
use \yii\web\UploadedFile;
use common\models\DriverAccountDetails;
use common\models\NotificationList;

/**
 * MainController implements the CRUD actions for APIs.
 */
class PassengerController extends \yii\base\Controller
{
    /*
     * Function : Login()
     * Description : The Restaurant's manager can login from application.
     * Request Params :Email address and password.
     * Response Params :
     * Author :Rutusha Joshi
     */

    public function actionLogin()
    {
        //Get all request parameter
        $amData = Common::checkRequestType();

        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('login_type');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        $requestParam = $amData['request_param'];

        // NORMAL LOGIN WITH EMAIL AND PASSWORD 
        if ($requestParam['login_type'] == "1") { 
            $amRequiredParams = array('email', 'password', 'device_id', 'login_type', 'device_type');
            $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

            // If any getting error in request paramter then set error message.
            if (!empty($amParamsResult['error'])) {
                $amResponse = Common::errorResponse($amParamsResult['error']);
                Common::encodeResponseJSON($amResponse);
            }

            if (($model = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password'])])) !== null) {

                if (($modell = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password']), 'role_id' => [Yii::$app->params['userroles']['super_admin'], Yii::$app->params['userroles']['admin'], Yii::$app->params['userroles']['driver']]])) !== null) {
                    $ssMessage = ' You are not authorize to login.';
                    $amResponse = Common::errorResponse($ssMessage);
                } else if (($model1 = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password']), 'status' => "0"])) !== null) {
                    $ssMessage = ' User has been deactivated. Please contact admin.';
                    $amResponse = Common::errorResponse($ssMessage);
                } else if (($model2 = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password']), 'is_email_code_verified' => "0"])) !== null) {
                    $ssMessage = ' Your Email is not verified.Please check your inbox to verify email';
                    $amResponse = Common::errorResponse($ssMessage);
                }else if (($model3 = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password']), 'is_phone_code_verified' => "0"])) !== null) {
                    $ssMessage = ' Your Phone is not verified.Please check your inbox to verify email';
                    $amResponse = Common::errorResponse($ssMessage);
                } else {
                    if (($device_model = DeviceDetails::findOne(['type' => "1", 'user_id' => $model->id])) === null) {
                        $device_model = new DeviceDetails();
                    }

                    $device_model->setAttributes($amData['request_param']);
                    $device_model->device_tocken = $requestParam['device_id'];
                    $device_model->type = $requestParam['device_type'];
                    $device_model->user_id = $model->id;
                    //  $device_model->created_at    = date( 'Y-m-d H:i:s' );
                    $device_model->save(false);
                    $ssAuthToken = Common::generateToken($model->id);
                    $model->auth_token = $ssAuthToken;
                    $model->save(false);

                    $ssMessage = 'successfully login.';
                    $amReponseParam['email'] = $model->email;
                    $amReponseParam['id'] = $model->id;
                    $amReponseParam['role'] = $model->role_id;
                    $amReponseParam['first_name'] = $model->first_name;
                    $amReponseParam['last_name'] = $model->last_name;
                    $amReponseParam['role'] = $model->role_id;
                    if($model->login_type == "1"){
                        $amReponseParam['photo'] = !empty($model->photo) && file_exists(Yii::getAlias('@root') . '/' . "uploads/profile_pictures/" . $model->photo) ? Yii::$app->params['root_url'] . '/' . "uploads/profile_pictures/" . $model->photo : Yii::$app->params['root_url'] . '/' . "no_image.png"; 
                    }else{
                        $amReponseParam['photo'] = !empty($model->photo) ? $model->photo : Yii::$app->params['root_url'] . '/' . "no_image.png";
                    }
                    $amReponseParam['device_token'] = $device_model->device_tocken;
                    $amReponseParam['device_type'] = $device_model->type;
                    $amReponseParam['auth_token'] = $ssAuthToken;
                    $amReponseParam['login_type'] = $model->login_type;
                    $amResponse = Common::successResponse($ssMessage, array_map('strval', $amReponseParam));
                }
            } else {
                $ssMessage = 'Invalid email OR password.';
                $amResponse = Common::errorResponse($ssMessage);
            }
        } else {
            $amRequiredParams = array('email', 'device_id', 'login_type', 'photo', 'first_name','last_name', 'device_type');
            $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

            // If any getting error in request paramter then set error message.
            if (!empty($amParamsResult['error'])) {
                $amResponse = Common::errorResponse($amParamsResult['error']);
                Common::encodeResponseJSON($amResponse);
            }
            //CHECK LOGIN EMAIL IS EXIST IN
            if (($model = Users::findOne(['email' => $requestParam['email']])) !== null) {
                if ($model->login_type == $requestParam['login_type']) {
                    if (($modell = Users::findOne(['email' => $requestParam['email'],'role_id' => [Yii::$app->params['userroles']['super_admin'], Yii::$app->params['userroles']['admin'], Yii::$app->params['userroles']['driver']]])) !== null) {
                        $ssMessage = ' You are not authorize to login.';
                        $amResponse = Common::errorResponse($ssMessage);
                    } else if (($model1 = Users::findOne(['email' => $requestParam['email'], 'status' => "0"])) !== null) {
                        $ssMessage = ' User has been deactivated. Please contact admin.';
                        $amResponse = Common::errorResponse($ssMessage);
                    } else {
                        if (($device_model = DeviceDetails::findOne(['type' => "1", 'user_id' => $model->id])) === null) {
                            $device_model = new DeviceDetails();
                        }

                        $device_model->setAttributes($amData['request_param']);
                        $device_model->device_tocken = $requestParam['device_id'];
                        $device_model->type = $requestParam['device_type'];

                        $device_model->user_id = $model->id;
                        //  $device_model->created_at    = date( 'Y-m-d H:i:s' );
                        $device_model->save(false);
                        $ssAuthToken = Common::generateToken($model->id);
                        $model->auth_token = $ssAuthToken;
                        $model->save(false);

                        $ssMessage = 'successfully login.';
                        $amReponseParam['email'] = $model->email;
                        $amReponseParam['id'] = $model->id;
                        $amReponseParam['role'] = $model->role_id;
                        $amReponseParam['first_name'] = $model->first_name;
                        $amReponseParam['last_name'] = $model->last_name;
                        $amReponseParam['role'] = $model->role_id;
                         $amReponseParam['photo'] = !empty($model->photo) ? $model->photo : Yii::$app->params['root_url'] . '/' . "no_image.png";
                        $amReponseParam['device_token'] = $device_model->device_tocken;
                        $amReponseParam['device_type'] = $device_model->type;
                        $amReponseParam['auth_token'] = $ssAuthToken;
                        $amReponseParam['login_type'] = $model->login_type;
                        $amResponse = Common::successResponse($ssMessage, array_map('strval', $amReponseParam));
                    }
                } else {
                    $model->login_type = $requestParam['login_type'];
                    $model->role_id = Yii::$app->params['userroles']['passanger'];
                    $model->first_name = $requestParam['first_name'];
                    $model->last_name = $requestParam['last_name'];
                    $model->photo = $requestParam['photo'];
                    $ssAuthToken = Common::generateToken($model->id);
                    $model->auth_token = $ssAuthToken;
                    $model->save(false);
                    if (($device_model = DeviceDetails::findOne(['type' => "1", 'user_id' => $model->id])) === null) {
                        $device_model = new DeviceDetails();
                    }
                    $device_model->setAttributes($amData['request_param']);
                    $device_model->device_tocken = $requestParam['device_id'];
                    $device_model->type = $requestParam['device_type'];
                    $device_model->user_id = $model->id;
                    //  $device_model->created_at    = date( 'Y-m-d H:i:s' );
                    $device_model->save(false);
                    $ssMessage = 'successfully login.';
                    $amReponseParam['email'] = $model->email;
                    $amReponseParam['id'] = $model->id;
                    $amReponseParam['role'] = $model->role_id;
                    $amReponseParam['first_name'] = $model->first_name;
                    $amReponseParam['last_name'] = $model->last_name;
                    $amReponseParam['role'] = $model->role_id;
                    $amReponseParam['photo'] = !empty($model->photo) ? $model->photo : "";
                    $amReponseParam['device_token'] = $device_model->device_tocken;
                    $amReponseParam['device_type'] = $device_model->type;
                    $amReponseParam['auth_token'] = $ssAuthToken;
                    $amReponseParam['login_type'] = $model->login_type;
                    $amResponse = Common::successResponse($ssMessage, array_map('strval', $amReponseParam));

                }
            } else {
                $model = new Users();
                $model->email = $requestParam['email'];
                $model->login_type = $requestParam['login_type'];
                $ssAuthToken = Common::generateToken($model->id);
                $model->auth_token = $ssAuthToken;
                $model->role_id = Yii::$app->params['userroles']['passanger'];
                $model->first_name = $requestParam['first_name'];
                $model->last_name = $requestParam['last_name'];
                $model->photo = $requestParam['photo'];
                $model->is_email_code_verified = 1;
                $model->is_phone_code_verified = 1;
                $model->save(false);
                if (($device_model = DeviceDetails::findOne(['type' => "1", 'user_id' => $model->id])) === null) {
                    $device_model = new DeviceDetails();
                }
                $device_model->setAttributes($amData['request_param']);
                $device_model->device_tocken = $requestParam['device_id'];
                $device_model->type = $requestParam['device_type'];
                $device_model->user_id = $model->id;
                //  $device_model->created_at    = date( 'Y-m-d H:i:s' );
                $device_model->save(false);
                $ssMessage = 'successfully login.';
                $amReponseParam['email'] = $model->email;
                $amReponseParam['id'] = $model->id;
                $amReponseParam['role'] = $model->role_id;
                $amReponseParam['first_name'] = $model->first_name;
                $amReponseParam['last_name'] = $model->last_name;
                $amReponseParam['role'] = $model->role_id;
                $amReponseParam['photo'] = !empty($model->photo) ? $model->photo : "";
                $amReponseParam['device_token'] = $device_model->device_tocken;
                $amReponseParam['device_type'] = $device_model->type;
                $amReponseParam['auth_token'] = $ssAuthToken;
                $amReponseParam['login_type'] = $model->login_type;
                $amResponse = Common::successResponse($ssMessage, array_map('strval', $amReponseParam));
            }
        }

        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

    /*
     * Function : SignUp()
     * Description : new user singup.
     * Request Params : irst_name,last_name,email address,contact_no
     * Response Params : user_id,firstname,email,last_name, email,status
     * Author : Rutusha Joshi
     */

    public function actionSignUp()
    {
        //Get all request parameter
        $amData = Common::checkRequestType();
        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('first_name','last_name', 'email', 'password','phone','device_id', 'device_type');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }

        $requestParam = $amData['request_param'];
        $requestFileparam = $amData['file_param'];
        if(empty($requestFileparam['photo']['name'])){
            $amResponse = Common::errorResponse("Please upload profile picture");
            Common::encodeResponseJSON($amResponse);
        }
            if (!empty(Users::findOne(["email" => $requestParam['email']]))) {
                $amResponse = Common::errorResponse("This Email id is already registered.");
                Common::encodeResponseJSON($amResponse);
            }
            if (!empty(Users::findOne(["phone" => $requestParam['phone']]))) {
            $amResponse = Common::errorResponse("Phone you entered is already registered by other user.");
            Common::encodeResponseJSON($amResponse);
            }
         $model = new Users();
        // Database field
        $model->first_name = $requestParam['first_name'];
        $model->last_name = $requestParam['last_name'];
        $model->email = $requestParam['email'];
        $model->password = md5($requestParam['password']);
        $model->phone = !empty($requestParam['phone']) ? Common::clean_special_characters($requestParam['phone']) : "";
        $SnRandomNumber = rand(1111,9999);
        $model->email_verification_code = $SnRandomNumber;
        $model->role_id = Yii::$app->params['userroles']['passanger'];
        $model->status = Yii::$app->params['user_status_value']['active'];
        $ssAuthToken = Common::generateToken($model->id);
        $model->auth_token = $ssAuthToken;
        $model->generateAuthKey();

        Yii::$app->urlManager->createUrl(['site/email-verify', 'verify' => base64_encode($model->email_verification_code), 'e' => base64_encode($model->email)]);
        $email_verify_link = Yii::$app->params['root_url'] . '/site/email-verify?verify=' . base64_encode($model->email_verification_code) . '&e=' . base64_encode($model->email);
        $model->is_phone_code_verified = "1";
         if (isset($requestFileparam['photo']['name'])) {

            $model->photo = UploadedFile::getInstanceByName('photo');
            $Modifier = md5(($model->photo));
            $OriginalModifier = $Modifier . rand(11111, 99999);
            $Extension = $model->photo->extension;
            $model->photo->saveAs(__DIR__ . "../../../uploads/profile_pictures/" . $OriginalModifier . '.' . $model->photo->extension);
            $model->photo = $OriginalModifier . '.' . $Extension;
        }
        if ($model->save(false)) {
            // Device Registration
            if (($device_model = Devicedetails::findOne(['type' => $amData['request_param']['device_type'], 'user_id' => $model->id])) === null) {
                $device_model = new Devicedetails();
            }

            $device_model->setAttributes($amData['request_param']);
            $device_model->device_tocken = $requestParam['device_id'];
            $device_model->type = $requestParam['device_type'];
            $device_model->user_id = $model->id;
            $device_model->save(false);

            ///////////////////////////////////////////////////////////
            //Get email template from database for sign up WS
            ///////////////////////////////////////////////////////////
            if (empty($ssEmail)) {
                $ssEmail = $model->email;
            }
            if (empty($requestParam['user_id']) || ($ssEmail != $requestParam['email'])) {
                $emailformatemodel = EmailFormat::findOne(["title" => 'user_registration', "status" => '1']);
                if ($emailformatemodel) {

                    //create template file
                    $AreplaceString = array('{password}' => $requestParam['password'], '{username}' => $model->first_name." ".$model->last_name, '{email}' => $model->email, '{email_verify_link}' => $email_verify_link);

                    $body = Common::MailTemplate($AreplaceString, $emailformatemodel->body);
                    $ssSubject = $emailformatemodel->subject;
                    //send email for new generated password
                    $ssResponse = Common::sendMail($model->email, Yii::$app->params['adminEmail'], $ssSubject, $body);

                }
            }
            $ssMessage = 'You are successfully Registered.';
            $amReponseParam['email'] = $model->email;
            $amReponseParam['id'] = $model->id;
            $amReponseParam['first_name'] = $model->first_name;
            $amReponseParam['last_name'] = $model->last_name;
            $amReponseParam['phone'] = $model->phone;
            $amReponseParam['role'] = $model->role_id;
            //$amReponseParam['phone'] = $model->phone;
            $amReponseParam['email_verification_code'] = $model->email_verification_code;
            $amReponseParam['is_phone_code_verified'] = $model->is_phone_code_verified;
            $amReponseParam['photo'] = !empty($model->photo) && file_exists(Yii::getAlias('@root') . '/' . "uploads/profile_pictures/" . $model->photo) ? Yii::$app->params['root_url'] . '/' . "uploads/profile_pictures/" . $model->photo : Yii::$app->params['root_url'] . '/' . "no_image.png";
            $amReponseParam['device_token'] = $device_model->device_tocken;
            $amReponseParam['device_token'] = $device_model->device_tocken;
            $amReponseParam['device_type'] = Yii::$app->params['device_type_value'][$device_model->type];
            /*   $amReponseParam['gcm_registration_id'] = !empty($device_model->gcm_id) ? $device_model->gcm_id : "";*/
            $amReponseParam['auth_token'] = $ssAuthToken;

            $amResponse = Common::successResponse($ssMessage, array_map('strval', $amReponseParam));
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

    /*
     * Function : ChangePassword()
     * Description : user can change password
     * Request Params : user_id,old_password, new_password
     * Response Params : success or error message
     * Author : Rutusha Joshi
     */

        public function actionChangePassword()
    {

        $amData = Common::checkRequestType();

        $amResponse = array();
        $ssMessage = '';
        // Check required validation for request parameter.
        $amRequiredParams = array('old_password', 'new_password', 'user_id');

        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {

            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        $requestParam = $amData['request_param'];
        // Check User Status
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);

        if (($model = Users::findOne(['id' => $requestParam['user_id'], 'password' => md5($requestParam['old_password']), 'status' => '1'])) !== null) {

            $model->password = md5($amData['request_param']['new_password']);
            if ($model->save()) {
                $ssMessage = 'Your password has been changed successfully.';
                $amReponseParam['user_id'] = $model->id;
                $amReponseParam['user_email'] = $model->email;
                $amResponse = Common::successResponse($ssMessage, array_map('strval', $amReponseParam));
            }
        } else {
            $ssMessage = 'Old Password is wrong';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

    /*
     * Function : ForgotPassword()
     * Description : if user can forgot passord so send password by mail.
     * Request Params : email,auth_token
     * Response Params : success or error message
     * Author : Rutusha Joshi
     */

    public function actionForgotPassword()
    {

        $amData = Common::checkRequestType();
        $amResponse = array();

        $ssMessage = '';
        // Check required validation for request parameter.
        $amRequiredParams = array('user_email');

        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }

        $requestParam = $amData['request_param'];

        // Check User Status

        if (($omUsers = Users::findOne(['email' => $requestParam['user_email'], 'status' => Yii::$app->params['user_status_value']['active']])) !== null) {

            if (!Users::isPasswordResetTokenValid($omUsers->password_reset_token)) {
                $token = Users::generatePasswordResetToken();
                $omUsers->password_reset_token = $token;
                if (!$omUsers->save(false)) {
                    return false;
                }
            }
            $resetLink = Yii::$app->params['root_url'] . "/site/reset-password?token=" . $omUsers->password_reset_token;

            $emailformatemodel = EmailFormat::findOne(["title" => 'reset_password', "status" => '1']);
            if ($emailformatemodel) {

                //create template file
                $AreplaceString = array('{resetLink}' => $resetLink, '{username}' => $omUsers->first_name." ".$omUsers->last_name);
                $body = Common::MailTemplate($AreplaceString, $emailformatemodel->body);

                //send email for new generated password
                $mail = Common::sendMailToUser($omUsers->email, Yii::$app->params['adminEmail'], $emailformatemodel->subject, $body);
            }
            if ($mail == 1) {
                $amReponseParam['user_email'] = $omUsers->email;
                $ssMessage = 'Email has been sent successfully please check your email. ';
                $amResponse = Common::successResponse($ssMessage, $amReponseParam);
            } else {
                $ssMessage = 'Email could not be sent successfully try again later.';
                $amResponse = Common::errorResponse($ssMessage);
            }
        } else {
            $ssMessage = 'Please enter valid email address which is provided during sign up.';
            $amResponse = Common::errorResponse($ssMessage);
        }

        Common::encodeResponseJSON($amResponse);
    }

    /*
     * Function : Logout()
     * Description : Log out
     * Request Params : user_id,auth_token
     * Response Params :
     * Author : Rutusha Joshi
     */

    // For Geting Daily data by date
    public function actionLogout()
    {
        $amData = Common::checkRequestType();
        $amResponse = array();
        $ssMessage = '';
        $amRequiredParams = array('user_id', 'device_id');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);
        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        $requestParam = $amData['request_param'];
        // Check User Status
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);

        $userModel = Users::findOne(['id' => $requestParam['user_id']]);
        if (!empty($userModel)) {
            if (($device_model = Devicedetails::findOne(['device_tocken' => $amData['request_param']['device_id'], 'user_id' => $requestParam['user_id']])) !== null) {
                $device_model->delete();
                $userModel->auth_token = "";
                $userModel->save(false);
                $ssMessage = 'Logout successfully';
                $amResponse = Common::successResponse($ssMessage, $amReponseParam = '');
            } else {
                $ssMessage = 'Your deivce token is invalid.';
                $amResponse = Common::errorResponse($ssMessage);
            }
        } else {
            $ssMessage = 'Invalid user_id';
            $amResponse = Common::errorResponse($ssMessage);
        }
        Common::encodeResponseJSON($amResponse);
    }
    /*
     * Function : EditProfile()
     * Description : Edit User Profile
     * Request Params : university_id,first_name,last_name,email address,contact_no
     * Response Params : user_id,firstname,email,last_name, email,status,created_at
     * Author : Rutusha Joshi
     */

    public function actionEditProfile()
    {
        //Get all request parameter
        $amData = Common::checkRequestType();
        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id', 'first_name', 'last_name', 'email', 'phone');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }

        $requestParam = $amData['request_param'];
        $requestFileparam = $amData['file_param'];
        //Check User Status//
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        if (!empty($requestParam['user_id'])) {

            if (!empty(Users::find()->where("email = '" . $requestParam['email'] . "' AND id != '" . $requestParam['user_id'] . "'")->one())) {
                $amResponse = Common::errorResponse("This Email id is already registered.");
                Common::encodeResponseJSON($amResponse);
            }
            if (!empty(Users::find()->where("phone = '" . $requestParam['phone'] . "' AND id != '" . $requestParam['user_id'] . "'")->one())) {

                $amResponse = Common::errorResponse("Phone Number you entered is already registered by other driver.");
                Common::encodeResponseJSON($amResponse);
            }

            $snUserId = $requestParam['user_id'];
            $model = Users::findOne(["id" => $snUserId]);
            if (!empty($model)) {
                $old_image = $model->photo;
                // Database field
                $model->first_name = $requestParam['first_name'];
                $model->last_name = $requestParam['last_name'];
                $model->email = !empty($requestParam['email']) ? $requestParam['email'] : "";
                $model->phone = !empty($requestParam['phone']) ? $requestParam['phone'] : '';
        if (isset($requestFileparam['photo']['name']) && !empty($requestFileparam['photo']['name'])) {


            $model->photo = UploadedFile::getInstanceByName('photo');
            $Modifier = md5(($model->photo));
            $OriginalModifier = $Modifier . rand(11111, 99999);
            $Extension = $model->photo->extension;
            $model->photo->saveAs(__DIR__ . "../../../uploads/profile_pictures/" . $OriginalModifier . '.' . $model->photo->extension);
            $model->photo = $OriginalModifier . '.' . $Extension;
        }
                if ($model->save(false)) {
            if (isset($requestFileparam['photo']['name']) && !empty($requestFileparam['photo']['name']) && !empty($old_image) && file_exists(Yii::getAlias('@root') . '/uploads/profile_pictures/' . $old_image)) {
                    unlink(Yii::getAlias('@root') . '/uploads/profile_pictures/' . $old_image);
                }
                 $ssMessage = 'Your profile has been updated successfully.';
                 $amReponseParam['email'] = $model->email;
                 $amReponseParam['id'] = $model->id;
                 $amReponseParam['first_name'] = $model->first_name;
                 $amReponseParam['last_name'] = $model->last_name;
                 $amReponseParam['phone'] = $model->phone;
                 $amReponseParam['role'] = $model->role_id;
            //$amReponseParam['phone'] = $model->phone;
                 $amReponseParam['email_verification_code'] = $model->email_verification_code;
                 $amReponseParam['is_phone_code_verified'] = $model->is_phone_code_verified;
                     if($model->login_type == "1"){
                        $amReponseParam['photo'] = !empty($model->photo) && file_exists(Yii::getAlias('@root') . '/' . "uploads/profile_pictures/" . $model->photo) ? Yii::$app->params['root_url'] . '/' . "uploads/profile_pictures/" . $model->photo : Yii::$app->params['root_url'] . '/' . "no_image.png"; 
                    }else{
                        $amReponseParam['photo'] = !empty($model->photo) ? $model->photo : Yii::$app->params['root_url'] . '/' . "no_image.png";
                    }
                 $device_model = Devicedetails::findOne(['user_id' => $model->id]);
                 $amReponseParam['device_token'] = (!empty($device_model) && !empty($device_model->device_tocken)) ? $device_model->device_tocken : "";
                 $amReponseParam['device_type'] = (!empty($device_model) && !empty($device_model->type)) ? Yii::$app->params['device_type_value'][$device_model->type] : "";
            /*   $amReponseParam['gcm_registration_id'] = !empty($device_model->gcm_id) ? $device_model->gcm_id : "";*/
                 $amReponseParam['auth_token'] = $model->auth_token;

                $amResponse = Common::successResponseLogin($ssMessage, array_map('strval', $amReponseParam),$model->id,$model->auth_token);
                }
            } else {
                $ssMessage = 'Invalid User.';
                $amResponse = Common::errorResponse($ssMessage);
            }
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }


      public function actionGetNotificationList()
    {
        //Get all request parameter
        $amData = Common::checkRequestType();
        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }

        $requestParam = $amData['request_param'];
        //Check User Status//
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        $snUserId = $requestParam['user_id'];
        $model = Users::findOne($snUserId);
        if (!empty($model)) {
            $notificationList = NotificationList::find()->where(["user_id" => $requestParam['user_id']])->asArray()->All();
            if (!empty($notificationList)) {
                $ssMessage = 'Notifications List';
                $amReponseParam = $notificationList;
                $amResponse = Common::successResponse($ssMessage, $amReponseParam);
            } else {
                $ssMessage = 'Notifications not found';
                $amResponse = Common::successResponse($ssMessage, $amReponseParam);
            }
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

        public function actionGetVehicleDetails()
    {
        //Get all request parameter
        $amData = Common::checkRequestType();
        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id','vehicle_id');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }

        $requestParam = $amData['request_param'];
        //Check User Status//
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        $snUserId = $requestParam['user_id'];
        $model = Users::findOne($snUserId);
        if (!empty($model)) {
            $checkVehicleId = VehicleDetails::find()->with('vehicleType')->where(['user_id'=>$requestParam['user_id'],"id"=>$requestParam['vehicle_id']])->asArray()->all();
            if(!empty($checkVehicleId)){
                $vahicle = $checkVehicleId[0];
                        $vahicle['vehicle_image_front'] = Common::get_driver_image_path($vahicle['vehicle_image_front']);
                        $vahicle['vehicle_image_back'] = Common::get_driver_image_path($vahicle['vehicle_image_back']);
                        $vahicle['driver_license_image_front'] = Common::get_driver_image_path($vahicle['driver_license_image_front']);
                        $vahicle['driver_license_image_back'] = Common::get_driver_image_path($vahicle['driver_license_image_back']);
                        $vahicle['vehicle_registration_image_front'] = Common::get_driver_image_path($vahicle['vehicle_registration_image_front']);
                        $vahicle['vehicle_registration_image_back'] = Common::get_driver_image_path($vahicle['vehicle_registration_image_back']);
                        $ssMessage = 'Vehicle Details';
                        $amReponseParam = $vahicle;
                        $amResponse = Common::successResponse($ssMessage, $amReponseParam);
        }else{
            $ssMessage = 'Invalid Vehicle.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

      public function actionRefreshDeviceToken()
    {

        $amData = Common::checkRequestType();

        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id', 'device_token');

        $amParamsResult = Common::checkRequiredParams($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        $requestParam = $amData['request_param'];
        //Check User Status//
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        /*  $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken);*/
        $oModelUser = Users::findOne($requestParam['user_id']);
        if (!empty($oModelUser)) {
            $deviceModel = DeviceDetails::find()->where(["user_id" => $requestParam['user_id']])->one();
            $deviceModel->device_tocken = $requestParam['device_token'];
            $deviceModel->save(false);
            $deviceModel->gcm_id = !empty($deviceModel->gcm_id) ? $deviceModel->gcm_id : "";
            $ssMessage = "Device Token updated successfully.";
            $amReponseParam = $deviceModel;
            $amResponse = Common::successResponse($ssMessage, $amReponseParam);
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }
}
