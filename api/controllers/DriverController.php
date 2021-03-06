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
class DriverController extends \yii\base\Controller
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
        $amRequiredParams = array('email', 'password', 'device_id', 'device_type');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }

        $requestParam = $amData['request_param'];

        if (($model = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password'])])) !== null) {

            if (($modell = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password']), 'role_id' => [Yii::$app->params['userroles']['super_admin'], Yii::$app->params['userroles']['admin'], Yii::$app->params['userroles']['passanger']]])) !== null) {
                $ssMessage = ' You are not authorize to login.';
                $amResponse = Common::errorResponse($ssMessage);
            } else if (($model1 = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password']), 'status' => "0"])) !== null) {
                $ssMessage = ' User has been deactivated. Please contact admin.';
                $amResponse = Common::errorResponse($ssMessage);
            } else if (($model2 = Users::findOne(['email' => $requestParam['email'], 'password' => md5($requestParam['password']), 'is_email_code_verified' => "0"])) !== null) {
                $ssMessage = ' Your Email is not verified.Please check your inbox to verify email';
                $amResponse = Common::errorResponse($ssMessage);
            }else {
                if (($device_model = DeviceDetails::findOne(['type' => $requestParam['device_type'], 'user_id' => $model->id])) === null) {
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
                $vehicleDetails = VehicleDetails::find()->where(['user_id'=>$model->id])->one();
                if(empty($vehicleDetails)){
                    $ssMessage = 'Successfully login.Please complete step 3 for Registration for adding vehicel details';
                    $step3 = "0";
                }else{
                    $ssMessage = "successfully Login.";
                    $step3 = "1";
                }
                 $amReponseParam['step3'] = $step3;
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
                 $amReponseParam['device_type'] = Yii::$app->params['device_type_value'][$device_model->type];
                 $amReponseParam['auth_token'] = $ssAuthToken;

                $amResponse = Common::successResponseLogin($ssMessage, array_map('strval', $amReponseParam),$model->id,$ssAuthToken);
            }
        } else {
            $ssMessage = 'Invalid email OR password.';
            $amResponse = Common::errorResponse($ssMessage);
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
        $model->role_id = Yii::$app->params['userroles']['driver'];
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
            $vehicleDetails = VehicleDetails::find()->where(['user_id'=>$model->id])->one();
                if(empty($vehicleDetails)){
                    $step3 = "0";
                }else{
                    $step3 = "1";
                }
            $amReponseParam['step3'] = $step3;
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
            $amReponseParam['device_type'] = Yii::$app->params['device_type_value'][$device_model->type];
            /*   $amReponseParam['gcm_registration_id'] = !empty($device_model->gcm_id) ? $device_model->gcm_id : "";*/
            $amReponseParam['auth_token'] = $ssAuthToken;

            $amResponse = Common::successResponse($ssMessage, array_map('strval', $amReponseParam));
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

     public function actionAddVehicleDetails()
    {

        $amData = Common::checkRequestType();

        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id','name','vehicle_type_id','seat_capacity','vehicle_registration_no');
        $amRequiredFileParams = array('vehicle_image_front','vehicle_image_back','driver_license_image_front','driver_license_image_back','vehicle_registration_image_front','vehicle_registration_image_back');
        $amParamsResult = Common::checkRequiredParams($amData['request_param'], $amRequiredParams);
         $amParamsResultFiles = Common::checkRequiredFileParams($amData['file_param'], $amRequiredFileParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])){
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        if (!empty($amParamsResultFiles['error'])){
            $amResponse = Common::errorResponse($amParamsResultFiles['error']);
            Common::encodeResponseJSON($amResponse);
        }
        
        $requestParam = $amData['request_param'];
        //p($requestParam,0);
        $requestFileparam = $amData['file_param'];
       // p($requestFileparam);
        // If any getting error in request paramter
        if(empty($requestFileparam['vehicle_image_front']) || empty($requestFileparam['vehicle_image_back']) || empty($requestFileparam['driver_license_image_front']) || empty($requestFileparam['driver_license_image_back']) || empty($requestFileparam['vehicle_registration_image_front']) || empty($requestFileparam['vehicle_registration_image_back'])){
             $ssMessage = 'Please upload images';
                $amResponse = Common::errorResponse($ssMessage);
                Common::encodeResponseJSON($amResponse);
        }
        //Check User Status//
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        $oModelUser = Users::findOne($requestParam['user_id']);
        if (!empty($oModelUser)) {
                $checkVehicleType = VehicleTypes::findOne($requestParam['vehicle_type_id']);
                if(!empty($checkVehicleType)){
                $vehicleDefaultSet = VehicleDetails::updateAll(['is_default' => '0'], ['user_id' => $requestParam['user_id']]);
                $vehicleDetails = new VehicleDetails;
                $vehicleDetails->user_id = $requestParam['user_id'];
                $vehicleDetails->name = $requestParam['name'];
                $vehicleDetails->vehicle_type_id = $requestParam['vehicle_type_id'];
                $vehicleDetails->seat_capacity = $requestParam['seat_capacity'];
                $vehicleDetails->vehicle_registration_no = $requestParam['vehicle_registration_no'];
                $vehicleDetails->status = "1";
                $vehicleDetails->is_default = "1";

                foreach ($requestFileparam as $key => $value) {
                    $vehicleDetails->$key = UploadedFile::getInstanceByName($key);
                    $Modifier = md5(($vehicleDetails->$key));
                    $OriginalModifier = $Modifier . rand(11111, 99999);
                    $Extension = $vehicleDetails->$key->extension;
                    $vehicleDetails->$key->saveAs(__DIR__ . "../../../uploads/driver_images/" . $OriginalModifier . '.' . $vehicleDetails->$key->extension);
                    $vehicleDetails->$key = $OriginalModifier . '.' . $Extension;
                }
                if($vehicleDetails->save(false)){

                $emailformatemodel = EmailFormat::findOne(["title" => 'approve_vehicle', "status" => '1']);
                if ($emailformatemodel) {

                    //create template file
                    $AreplaceString = array('{driver_name}' => $oModelUser->first_name." ".$oModelUser->last_name,'{vehicle_name}'=>$vehicleDetails->name);

                    $body = Common::MailTemplate($AreplaceString, $emailformatemodel->body);
                    $ssSubject = $emailformatemodel->subject;
                    //send email for new generated password
                    $ssResponse = Common::sendMail(Yii::$app->params['marveladminEmail'], Yii::$app->params['adminEmail'], $ssSubject, $body);

                }
                $vehicleDetails->vehicle_image_front = Common::get_driver_image_path($vehicleDetails->vehicle_image_front);
                $vehicleDetails->vehicle_image_back = Common::get_driver_image_path($vehicleDetails->vehicle_image_back);
                $vehicleDetails->driver_license_image_front = Common::get_driver_image_path($vehicleDetails->driver_license_image_front);
                $vehicleDetails->driver_license_image_back = Common::get_driver_image_path($vehicleDetails->driver_license_image_back);
                $vehicleDetails->vehicle_registration_image_front = Common::get_driver_image_path($vehicleDetails->vehicle_registration_image_front);
                $vehicleDetails->vehicle_registration_image_back = Common::get_driver_image_path($vehicleDetails->vehicle_registration_image_back);
                $amReponseParam = $vehicleDetails;
                $ssMessage = "Your Vehicle Details added successfully.";
                $amResponse = Common::successResponse($ssMessage,$amReponseParam);
                }
            }else{
                $ssMessage = 'Invalid vehicle type.';
                $amResponse = Common::errorResponse($ssMessage);
            }
            
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

    /*
     * Function : verifyEmail()
     * Description : email verification
     * Request Params : verification_code,user_id
     * Author : Rutusha Joshi
     */

    public function actionVerifyCode()
    {
        $amResponse = $amResponseData = [];
        //Get all request parameter
        $amData = Common::checkRequestType();

        // Check required validation for request parameter.
        $amRequiredParams = array('verification_code', 'user_id');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }

        $requestParam = $amData['request_param'];
        $snUserId = $requestParam['user_id'];
        $ssCode = $requestParam['verification_code'];

        $modelUsers = Users::findOne(["id" => $snUserId, "verification_code" => $ssCode]);
        if (!empty($modelUsers)) {
            $modelUsers->is_code_verified = 1;
            $modelUsers->save(false);
            $amResponseData = [
                'is_mobile_verified' => '1',
            ];
            $amResponse = Common::successResponse("Code verified successfully.", $amResponseData);
        } else {
            $amResponseData = [
                'is_mobile_verified' => '0',
            ];
            $amResponse = Common::successResponse("Invalid verification code.", $amResponseData);
        }
        Common::encodeResponseJSON($amResponse);
    }

    /*
     * Function : ResendVerificationCode()
     * Description : Re send verification code
     * Request Params : 'user_id', 'phone','country_code'
     * Author : Rutusha Joshi
     */

    public function actionResendVerificationCode()
    {
        $amResponse = $amResponseData = [];
        //Get all request parameter
        $amData = Common::checkRequestType();

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id', 'phone');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }

        $requestParam = $amData['request_param'];
        $snUserId = $requestParam['user_id'];
        $ssPhone = $requestParam['phone'];

        $modelUsers = Users::findOne(["id" => $snUserId]);
        if (!empty($modelUsers)) {
            $SnRandomNumber = rand(1111, 9999);
            $Textmessage = "Your verification code is : " . $SnRandomNumber;
            // Common::sendSms( $Textmessage, "$requestParam[phone]" );
            $modelUsers->verification_code = $SnRandomNumber;
            $modelUsers->save(false);
            $amResponseData['Verification_code'] = $modelUsers->verification_code;
            $amResponse = Common::successResponse("Code sent successfully.", $amResponseData);
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
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
                $vehicleDetails = VehicleDetails::find()->where(['user_id'=>$model->id])->one();
                if(empty($vehicleDetails)){
                    $step3 = "0";
                }else{
                    $step3 = "1";
                }
                 $amReponseParam['step3'] = $step3;
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


    public function actionEditVehicleDetails()
    {
        //Get all request parameter
        $amData = Common::checkRequestType();
        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id','vehicle_id','name', 'vehicle_type_id', 'seat_capacity', 'vehicle_registration_no');
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
        $snUserId = $requestParam['user_id'];
        $model = Users::findOne(["id" => $snUserId]);
        if (!empty($model)) {
            $vehicleModel = VehicleDetails::find()->where(["id"=>$requestParam['vehicle_id']])->one();
            if(!empty($vehicleModel)){
                $vehicleModel->name = $requestParam['name'];
                $vehicleModel->vehicle_type_id = $requestParam['vehicle_type_id'];
                $vehicleModel->seat_capacity = $requestParam['seat_capacity'];
                $vehicleModel->vehicle_registration_no = $requestParam['vehicle_registration_no'];
                $old_vehicle_image_front = $vehicleModel->vehicle_image_front;
                $old_vehicle_image_back = $vehicleModel->vehicle_image_back;
                $old_driver_license_image_front = $vehicleModel->driver_license_image_front;
                $old_driver_license_image_back  = $vehicleModel->driver_license_image_back;
                $old_vehicle_registration_image_front  = $vehicleModel->vehicle_registration_image_front;
                $old_vehicle_registration_image_back  = $vehicleModel->vehicle_registration_image_back;
            if (isset($requestFileparam['vehicle_image_front']['name']) && !empty($requestFileparam['vehicle_image_front']['name'])) {
                $vehicleModel->vehicle_image_front = Common::uploadImage($vehicleModel,"vehicle_image_front",$old_vehicle_image_front);
            }
            if (isset($requestFileparam['vehicle_image_back']['name']) && !empty($requestFileparam['vehicle_image_back']['name'])) {
                $vehicleModel->vehicle_image_back = Common::uploadImage($vehicleModel,"vehicle_image_back",$old_vehicle_image_back);
            }
            if (isset($requestFileparam['driver_license_image_front']['name']) && !empty($requestFileparam['driver_license_image_front']['name'])) {
                $vehicleModel->driver_license_image_front = Common::uploadImage($vehicleModel,"driver_license_image_front",$old_driver_license_image_front);
            }
            if (isset($requestFileparam['driver_license_image_back']['name']) && !empty($requestFileparam['driver_license_image_back']['name'])) {
                $vehicleModel->driver_license_image_back = Common::uploadImage($vehicleModel,"driver_license_image_back",$old_driver_license_image_back);
            }
            if (isset($requestFileparam['vehicle_registration_image_front']['name']) && !empty($requestFileparam['vehicle_registration_image_front']['name'])) {
                $vehicleModel->vehicle_registration_image_front = Common::uploadImage($vehicleModel,"vehicle_registration_image_front",$old_vehicle_registration_image_front);
            }
            if (isset($requestFileparam['vehicle_registration_image_back']['name']) && !empty($requestFileparam['vehicle_registration_image_back']['name'])) {
                $vehicleModel->vehicle_registration_image_back = Common::uploadImage($vehicleModel,"vehicle_registration_image_back",$old_vehicle_registration_image_back);
            }
                if($vehicleModel->save(false)){
                    if($vehicleModel->is_approve == Yii::$app->params['is_approve_vehicle_value']['decline']){
                    $emailformatemodel = EmailFormat::findOne(["title" => 'approve_updated_vehicle', "status" => '1']);
                    if ($emailformatemodel) {

                        //create template file
                        $AreplaceString = array('{driver_name}' => $model->first_name." ".$model->last_name,'{vehicle_name}'=>$vehicleModel->name);

                        $body = Common::MailTemplate($AreplaceString, $emailformatemodel->body);
                        $ssSubject = $emailformatemodel->subject;
                        //send email for new generated password
                        $ssResponse = Common::sendMail(Yii::$app->params['marveladminEmail'], Yii::$app->params['adminEmail'], $ssSubject, $body);

                    }
                    }

                $vehicleModel->vehicle_image_front = Common::get_driver_image_path($vehicleModel->vehicle_image_front);
                $vehicleModel->vehicle_image_back = Common::get_driver_image_path($vehicleModel->vehicle_image_back);
                $vehicleModel->driver_license_image_front = Common::get_driver_image_path($vehicleModel->driver_license_image_front);
                $vehicleModel->driver_license_image_back = Common::get_driver_image_path($vehicleModel->driver_license_image_back);
                $vehicleModel->vehicle_registration_image_front = Common::get_driver_image_path($vehicleModel->vehicle_registration_image_front);
                $vehicleModel->vehicle_registration_image_back = Common::get_driver_image_path($vehicleModel->vehicle_registration_image_back);
                $amReponseParam = $vehicleModel;
                $ssMessage = "Vehicle Details Updated Successfully.";
                $amResponse = Common::successResponse($ssMessage,$amReponseParam);
                }
            }else{
                    $ssMessage = 'Vehicle Details not found';
                    $amResponse = Common::errorResponse($ssMessage);
            }
            } else {
                $ssMessage = 'Invalid User.';
                $amResponse = Common::errorResponse($ssMessage);
            }
    
        
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }
    /*
     * Function : GetUserDetails()
     * Description : Get User Details
     * Request Params : user_id
     * Response Params : user's details
     * Author : Rutusha Joshi
     */

    public function actionGetDriverVehicleDetails()
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
        $model = Users::findOne(["id" => $snUserId]);
        if (!empty($model)) {
            // Device Registration
            $ssMessage = 'User Profile Details.';
            $amResponse = Common::successResponse($ssMessage, array_map('strval', $amReponseParam));
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

    /*
     * Function :
     * Description : Reset Badge Count
     * Request Params :'user_id','auth_token'
     * Response Params :
     * Author :Rutusha Joshi
     */
    public function actionResetBadgeCount()
    {

        $amData = Common::checkRequestType();

        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id');

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
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        $oModelUser = Users::findOne($requestParam['user_id']);
        if (!empty($oModelUser)) {

            $oModelUser->badge_count = 0;
            $oModelUser->save(false);
            $ssMessage = "Badge count updated successfully.";
            $amResponse = Common::successResponse($ssMessage);
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

     public function actionGetVehicleTypes()
    {

        $amData = Common::checkRequestType();

        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id');
        $amParamsResult = Common::checkRequiredParams($amData['request_param'], $amRequiredParams);
        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])){
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        
        $requestParam = $amData['request_param'];
        //Check User Status//
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        $oModelUser = Users::findOne($requestParam['user_id']);
        if (!empty($oModelUser)) {
            $vehicleTypesArr = VehicleTypes::find()->where(['status'=>"1"])->asArray()->all();
            if(!empty($vehicleTypesArr)){
                $amReponseParam = $vehicleTypesArr;
                $ssMessage = "Vehicle Types list";
                $amResponse = Common::successResponse($ssMessage,$amReponseParam);
            }else{
                $ssMessage = "Vehicle Types not found";
                $amResponse = Common::successResponse($ssMessage,$amReponseParam);
            }
            
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

       public function actionAddDriverBankDetails()
    {
        //Get all request parameter
        $amData = Common::checkRequestType();
        $amResponse = $amReponseParam = [];
        // Check required validation for request parameter.
        $amRequiredParams = array('user_id', 'stripe_bank_account_holder_name', 'stripe_bank_account_holder_type', 'stripe_bank_routing_number', 'stripe_bank_account_number');
        $amParamsResult = Common::checkRequestParameterKey($amData['request_param'], $amRequiredParams);

        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])) {
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        $requestParam = $amData['request_param'];
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        //Check User Status//
        $snUserId = $requestParam['user_id'];
        $model = Users::findOne(["id" => $snUserId]);
        if (!empty($model)) {
            $AccountDetails = DriverAccountDetails::find()->where(["user_id" => $requestParam['user_id']])->one();
            if (!empty($AccountDetails)) {
                $ssMessage = 'Your account details are already added';
                $amResponse = Common::errorResponse($ssMessage);
                Common::encodeResponseJSON($amResponse);
            }
// Generate Stripe Bank account and connect account from the data
            \Stripe\Stripe::setApiKey("sk_test_xQWSZTWSuFJ7nXVEQtYdah7T00VZB1z5Fd");
            try {
                // first create bank token
                $bankToken = \Stripe\Token::create([
                    'bank_account' => [
                        'country' => 'US',
                        'currency' => 'usd',
                        'account_holder_name' => $requestParam['stripe_bank_account_holder_name'],
                        'account_holder_type' => $requestParam['stripe_bank_account_holder_type'],
                        'routing_number' => $requestParam['stripe_bank_routing_number'],
                        'account_number' => $requestParam['stripe_bank_account_number'],
                    ],
                ]);
                $account_holder_name = explode(" ", $requestParam['stripe_bank_account_holder_name']);
                $first_name = $account_holder_name[0];
                $last_name = $account_holder_name[1];
                // second create stripe account
                $stripeAccount = \Stripe\Account::create([
                    "type" => "custom",
                    "country" => "US",
                    "email" => $model->email,
                    "business_type" => "individual",
                    "business_profile" => [
                        "url" => "http://www.zenocraft.com",
                    ],
                    "individual" => [
                        "first_name" => $first_name,
                        "last_name" => $last_name,
                    ],
                    "requested_capabilities" => ['transfers'],
                ]);
                // third link the bank account with the stripe account
                $bankAccount = \Stripe\Account::createExternalAccount(
                    $stripeAccount->id, ['external_account' => $bankToken->id]
                );
                // Fourth stripe account update for tos acceptance
                \Stripe\Account::update(
                    $stripeAccount->id, [
                        'tos_acceptance' => [
                            'date' => time(),
                            'ip' => $_SERVER['REMOTE_ADDR'], // Assumes you're not using a proxy
                        ],
                    ]
                );
                $response = ["bankToken" => $bankToken->id, "stripeAccount" => $stripeAccount->id, "bankAccount" => $bankAccount->id];
                $accountDetailModel = new DriverAccountDetails();
                $accountDetailModel->user_id = $requestParam['user_id'];
                $accountDetailModel->stripe_bank_account_holder_name = $requestParam['stripe_bank_account_holder_name'];
                $accountDetailModel->stripe_bank_account_holder_type = $requestParam['stripe_bank_account_holder_type'];
                $accountDetailModel->stripe_bank_routing_number = $requestParam['stripe_bank_routing_number'];
                $accountDetailModel->stripe_bank_account_number = $requestParam['stripe_bank_account_number'];
                $accountDetailModel->stripe_bank_token = $response['bankToken'];
                $accountDetailModel->stripe_connect_account_id = $response['stripeAccount'];
                $accountDetailModel->stripe_bank_accout_id = $response['bankAccount'];
                $accountDetailModel->save(false);
                $amReponseParam = $accountDetailModel;
                $ssMessage = 'Stripe account detail successfully added.';
                $amResponse = Common::successResponse($ssMessage, $amReponseParam);

            } catch (\Exception $e) {
                p($e, 0);
                $ssMessage = 'Something went wrong';
                $amResponse = Common::errorResponse($ssMessage);
            }

        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

        public function actionGetMyVehicleList()
    {

        $amData = Common::checkRequestType();

        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id');
        $amParamsResult = Common::checkRequiredParams($amData['request_param'], $amRequiredParams);
        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])){
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        
        $requestParam = $amData['request_param'];
        //Check User Status//
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        $oModelUser = Users::findOne($requestParam['user_id']);
        if (!empty($oModelUser)) {
            $vehicleList = VehicleDetails::find()->with('vehicleType')->where(['user_id'=>$requestParam['user_id']])->asArray()->all();
            if(!empty($vehicleList)){
            array_walk($vehicleList, function ($arr) use (&$amResponseData) {
                $ttt = $arr;
                $ttt['admin_message'] = !empty($ttt['admin_message']) ? $ttt['admin_message'] : "";
                $ttt['vehicle_image_front'] = Common::get_driver_image_path($ttt['vehicle_image_front']);
                $ttt['vehicle_image_back'] = Common::get_driver_image_path($ttt['vehicle_image_back']);
                $ttt['driver_license_image_front'] = Common::get_driver_image_path($ttt['driver_license_image_front']);
                $ttt['driver_license_image_back'] = Common::get_driver_image_path($ttt['driver_license_image_back']);
                $ttt['vehicle_registration_image_front'] = Common::get_driver_image_path($ttt['vehicle_registration_image_front']);
                $ttt['vehicle_registration_image_back'] = Common::get_driver_image_path($ttt['vehicle_registration_image_back']);
                        $amResponseData[] = $ttt;
                        return $amResponseData;
                    });
                $amReponseParam = $amResponseData;
                $ssMessage = "My Vehicle list";
                $amResponse = Common::successResponse($ssMessage,$amReponseParam);
            }else{
                $ssMessage = "Vehicles not found";
                $amResponse = Common::successResponse($ssMessage,$amReponseParam);
            }
            
        } else {
            $ssMessage = 'Invalid User.';
            $amResponse = Common::errorResponse($ssMessage);
        }
        // FOR ENCODE RESPONSE INTO JSON //
        Common::encodeResponseJSON($amResponse);
    }

      public function actionSetDefaultVehicle()
    {

        $amData = Common::checkRequestType();

        $amResponse = $amReponseParam = [];

        // Check required validation for request parameter.
        $amRequiredParams = array('user_id','vehicle_id');
        $amParamsResult = Common::checkRequiredParams($amData['request_param'], $amRequiredParams);
        // If any getting error in request paramter then set error message.
        if (!empty($amParamsResult['error'])){
            $amResponse = Common::errorResponse($amParamsResult['error']);
            Common::encodeResponseJSON($amResponse);
        }
        
        $requestParam = $amData['request_param'];
        //Check User Status//
        Common::matchUserStatus($requestParam['user_id']);
        //VERIFY AUTH TOKEN
        $authToken = Common::get_header('auth_token');
        Common::checkAuthentication($authToken, $requestParam['user_id']);
        $oModelUser = Users::findOne($requestParam['user_id']);
        if (!empty($oModelUser)) {
            $checkVehicleId = VehicleDetails::find()->where(['user_id'=>$requestParam['user_id'],"id"=>$requestParam['vehicle_id']])->one();
            if(!empty($checkVehicleId)){
                $vehicleDefaultSet = VehicleDetails::updateAll(['is_default' => '0'], ['user_id' => $requestParam['user_id']]);
                $checkVehicleId->is_default = "1";
                $checkVehicleId->save(false);
                $vehicleList = VehicleDetails::find()->where(['user_id'=>$requestParam['user_id']])->asArray()->all();
                if(!empty($vehicleList)){
                    array_walk($vehicleList, function ($arr) use (&$amResponseData) {
                        $ttt = $arr;
                        $ttt['vehicle_image_front'] = Common::get_driver_image_path($ttt['vehicle_image_front']);
                        $ttt['vehicle_image_back'] = Common::get_driver_image_path($ttt['vehicle_image_back']);
                        $ttt['driver_license_image_front'] = Common::get_driver_image_path($ttt['driver_license_image_front']);
                        $ttt['driver_license_image_back'] = Common::get_driver_image_path($ttt['driver_license_image_back']);
                        $ttt['vehicle_registration_image_front'] = Common::get_driver_image_path($ttt['vehicle_registration_image_front']);
                        $ttt['vehicle_registration_image_back'] = Common::get_driver_image_path($ttt['vehicle_registration_image_back']);
                                $amResponseData[] = $ttt;
                                return $amResponseData;
                    });
                $amReponseParam = $amResponseData;
                $ssMessage = "Default Vehicle set successfully.";
                $amResponse = Common::successResponse($ssMessage,$amReponseParam);
            }else{
                $ssMessage = "Vehicles not found";
                $amResponse = Common::successResponse($ssMessage,$amReponseParam);
            }
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
