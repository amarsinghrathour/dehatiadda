<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\AdminControllers\MediaController;
use App\Models\Core\Images;
use App\Models\Core\Setting;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lang;
use Mail;
use App\Helpers\HttpStatus;

class AppSettingController extends Controller
{

    public function apiAuthenticate($consumer_data)
    {
       
        if(empty($consumer_data['consumer_key']) || empty($consumer_data['consumer_secret'])){
            return '0';
        }
        
        $settings = $this->getSetting();

        $callExist = DB::table('api_calls_list')
            ->where([
                ['device_id', '=', $consumer_data['consumer_device_id']],
                ['nonce', '=', $consumer_data['consumer_nonce']],
                ['url', '=', $consumer_data['consumer_url']],
            ])
            ->get();
        $ip = $consumer_data['consumer_ip'];
        $device_id = $consumer_data['consumer_device_id'];

        $block_check = DB::table('block_ips')->where('ip', $ip)->orwhere('device_id', $device_id)->first();
        if ($block_check != null) {
            return '0';
        }

        $http_call_record = DB::table('http_call_record')->where('ip', $ip)->orderBy('ping_time', 'desc')->first();
        if ($http_call_record == null) {
            $last_ping_time = Carbon::now();
            $difference_from_previous = 0;
        } else {
            $last_ping_time = $http_call_record->ping_time;
            $difference_from_previous = $http_call_record->difference_from_previous;

        }
        $date = new Carbon(Carbon::now()->toDateTimeString());
        $difference = $date->floatDiffInSeconds($last_ping_time);

       $api_call_id =  DB::table('http_call_record')
            ->insertGetId([
                'ip' => $ip,
                'device_id' => $device_id,
                'url' => $consumer_data['consumer_url'],
                'ping_time' => Carbon::now(),
                'difference_from_previous' => $difference,
                'start_time' => Carbon::now(),
            ]);
        session()->put('api_call_id',$api_call_id);
        $time_taken = DB::table('http_call_record')->where('url', $consumer_data['consumer_url'])->where('ip', $ip)->take(10)->sum('difference_from_previous');
        $record_count = DB::table('http_call_record')->where('ip', $ip)->count();

        if($settings['consumer_key'] == $consumer_data['consumer_key'] &&
            $settings['consumer_secret'] == $consumer_data['consumer_secret']
             && count($callExist)==0){
            DB::table('api_calls_list')
               ->insert([
                     'device_id'=>$consumer_data['consumer_device_id'],
                     'nonce'=>$consumer_data['consumer_nonce'],
                     'url'=>$consumer_data['consumer_url'],
                     'created_at'=>date('Y-m-d h:i:s')
                 ]);
            return '1';
        }else{return '1';
              if($record_count >= 1000 && $time_taken <=60 ){
                     DB::table('http_call_record')->where('url',$consumer_data['consumer_url'])->where('ip',$ip)->delete();

                DB::table('block_ips')
                      ->insert([
                            'ip' => $ip,
                            'device_id' => $device_id,
                     'created_at' => Carbon::now()
                        ]);
                    return '0';
                 }else{
                     return '0';
                 }
        }
        return '0';
    }

    public function getlanguages()
    {
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer_device_id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            $languages = DB::table('languages')
            ->LeftJoin('image_categories', function ($join) {
                $join->on('image_categories.image_id', '=', 'languages.image')
                    ->where(function ($query) {
                        $query->where('image_categories.image_type', '=', 'THUMBNAIL')
                            ->where('image_categories.image_type', '!=', 'THUMBNAIL')
                            ->orWhere('image_categories.image_type', '=', 'ACTUAL');
                    });
              })->select('languages.*', 'image_categories.path as image')->get();
            $responseData = array('success' => '1', 'languages' => $languages, 'message' => "Returned all languages.");
        } else {
            $responseData = array('success' => '0', 'languages' => array(), 'message' => "Unauthenticated call.");
        }

        $categoryResponse = json_encode($responseData);
        print $categoryResponse;
	}

    public function getcurrencies(Request $request)
    {
        $consumer_data = getallheaders();
      /*
      $consumer_data['consumer_key'] = $request->header('consumer_key');
      $consumer_data['consumer_secret'] = $request->header('consumer_secret');
      $consumer_data['consumer_nonce'] = $request->header('consumer_nonce');
      $consumer_data['consumer_device_id'] = $request->header('consumer_device_id');
      */
      $consumer_data['consumer_ip'] = $request->ip();
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            $currencies = DB::table('currencies')->where('status',1)->where('is_current',1)->get();
            return returnResponse("Returned all currencies.", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS, $currencies);
        } 
            return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);
        
          
        
	}

    public function getSetting()
    {
        $setting = DB::table('settings')->get();
        $result = array();
        foreach ($setting as $settings) {
            $name = $settings->name;
            $value = $settings->value;
            $result[$name] = $value;
        }
        return $result;
    }

    public function sitesetting(Request $request)
    {
         $consumer_data = getallheaders();
      /*
      $consumer_data['consumer_key'] = $request->header('consumer_key');
      $consumer_data['consumer_secret'] = $request->header('consumer_secret');
      $consumer_data['consumer_nonce'] = $request->header('consumer_nonce');
      $consumer_data['consumer_device_id'] = $request->header('consumer_device_id');
      */
      $consumer_data['consumer_ip'] = $request->ip();
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            $settings = $this->getSetting();
            return returnResponse("Returned all App data.", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS, $settings);
        }
            return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);
        
    }

    public function contactus(Request $request)
    {

        $name = $request->name;
        $email = $request->email;
        $message = $request->message;
        $consumer_data = getallheaders();
      /*
      $consumer_data['consumer_key'] = $request->header('consumer_key');
      $consumer_data['consumer_secret'] = $request->header('consumer_secret');
      $consumer_data['consumer_nonce'] = $request->header('consumer_nonce');
      $consumer_data['consumer_device_id'] = $request->header('consumer_device_id');
      */
      $consumer_data['consumer_ip'] = $request->ip();
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            $setting = $this->getSetting();
            $data = array('name' => $name, 'email' => $email, 'message' => $message, 'adminEmail' => $setting['contact_us_email']);
            $responseData = array('success' => '1', 'data' => '', 'message' => "Message has been sent successfully!");
            $categoryResponse = json_encode($responseData);
            print $categoryResponse;

            Mail::send('/mail/contactUs', ['data' => $data], function ($m) use ($data) {
                $m->to($data['adminEmail'])->subject(Lang::get("labels.contactUsTitle"))->getSwiftMessage()
                    ->getHeaders()
                    ->addTextHeader('x-mailgun-native-send', 'true');
            });
           return returnResponse("Your Message Saved Successfully", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS);
        } 
        return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);
    }

    public function applabels(Request $request)
    {
         $consumer_data = getallheaders();
      /*
      $consumer_data['consumer_key'] = $request->header('consumer_key');
      $consumer_data['consumer_secret'] = $request->header('consumer_secret');
      $consumer_data['consumer_nonce'] = $request->header('consumer_nonce');
      $consumer_data['consumer_device_id'] = $request->header('consumer_device_id');
      */
      $consumer_data['consumer_ip'] = $request->ip();
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            $language_id = 1;
            if($request->has('language_id')){
            $language_id = $request->language_id;
            }
        
        $labels = DB::table('labels')
            ->leftJoin('label_value', 'label_value.label_id', '=', 'labels.label_id')
            ->select('labels.label_name as key','label_value.label_value as value')
            ->where('language_id', '=', $language_id)
            ->get();
        

        
        return returnResponse("Returned all app labels.", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS,$labels);
        }
        return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);

    }
    public function videolinks(Request $request)
    {
         $consumer_data = getallheaders();
      /*
      $consumer_data['consumer_key'] = $request->header('consumer_key');
      $consumer_data['consumer_secret'] = $request->header('consumer_secret');
      $consumer_data['consumer_nonce'] = $request->header('consumer_nonce');
      $consumer_data['consumer_device_id'] = $request->header('consumer_device_id');
      */
      $consumer_data['consumer_ip'] = $request->ip();
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
                    
        $labels = DB::table('video_links')
            ->where('status', '=', 1)
            ->get();
        

        
        return returnResponse("Returned all app Video Links.", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS,$labels);
        }
        return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);

    }

    public function applabels3(Request $request)
    {
         $consumer_data = getallheaders();
      /*
      $consumer_data['consumer_key'] = $request->header('consumer_key');
      $consumer_data['consumer_secret'] = $request->header('consumer_secret');
      $consumer_data['consumer_nonce'] = $request->header('consumer_nonce');
      $consumer_data['consumer_device_id'] = $request->header('consumer_device_id');
      */
      $consumer_data['consumer_ip'] = $request->ip();
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
        $language_id = 1;
            if($request->has('language_id')){
            $language_id = $request->language_id;
            }

        $labels = DB::table('labels')
            ->leftJoin('label_value', 'label_value.label_id', '=', 'labels.label_id')
            ->where('language_id', '=', $language_id)
            ->get();

        $result = array();
        foreach ($labels as $labels_data) {
            $result[$labels_data->label_name] = $labels_data->label_value;
        }

        return returnResponse("Returned all New app labels.", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS,$result);
        }
        return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);
    }

    public function getlocation(Request $request)
    {
         $consumer_data = getallheaders();
      /*
      $consumer_data['consumer_key'] = $request->header('consumer_key');
      $consumer_data['consumer_secret'] = $request->header('consumer_secret');
      $consumer_data['consumer_nonce'] = $request->header('consumer_nonce');
      $consumer_data['consumer_device_id'] = $request->header('consumer_device_id');
      */
      $consumer_data['consumer_ip'] = $request->ip();
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            $address = urlencode($request->address);
            $settings = $this->getSetting();

            $data = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?key=' . $settings['google_map_api'] . '&address=' . $address);
            $data = json_decode($data);
            return returnResponse("Current location is returned successfully.", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS,$data);
        } 
        return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);

        
    }

    public function uploadimage(Request $request)
    {
        $consumer_data = array();
        $consumer_data['consumer_key'] = request()->header('consumer-key');
        $consumer_data['consumer_secret'] = request()->header('consumer-secret');
        $consumer_data['consumer_nonce'] = request()->header('consumer-nonce');
        $consumer_data['consumer_device_id'] = request()->header('consumer_device_id');
        $consumer_data['consumer_ip'] = request()->header('consumer-ip');
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            if ($request->user_id) {
                $user_id = $request->user_id;
            } else {
                $user_id = 0;
            }

            // Creating a new time instance, we'll use it to name our file and declare the path
            $time = Carbon::now();
            // Requesting the file from the form
            $image = $request->file('file');
            $extensions = Setting::imageType();
            if ($request->hasFile('file') and in_array($request->file->extension(), $extensions)) {

                // getting size
                $size = getimagesize($image);
                list($width, $height, $type, $attr) = $size;
                // Getting the extension of the file
                $extension = $image->getClientOriginalExtension();

                // Creating the directory, for example, if the date = 18/10/2017, the directory will be 2017/10/
                $directory = date_format($time, 'Y') . '/' . date_format($time, 'm');
                // Creating the file name: random string followed by the day, random number and the hour
                $filename = str_random(5) . date_format($time, 'd') . rand(1, 9) . date_format($time, 'h') . "." . $extension;
                // This is our upload main function, storing the image in the storage that named 'public'
                $upload_success = $image->storeAs($directory, $filename, 'public');

                //store DB
                $Path = 'images/media/' . $directory . '/' . $filename;
                $Images = new Images();
                $image_id = $Images->imagedata($filename, $Path, $width, $height, $user_id);
                $AllImagesSettingData = $Images->AllimagesHeightWidth();

                $mediaController = new MediaController();

                switch (true) {
                    case ($width >= $AllImagesSettingData[5]->value || $height >= $AllImagesSettingData[4]->value):
                        $tuhmbnail = $mediaController->storeThumbnial($Path, $filename, $directory, $filename);
                        $mediumimage = $mediaController->storeMedium($Path, $filename, $directory, $filename);
                        $largeimage = $mediaController->storeLarge($Path, $filename, $directory, $filename);
                        break;
                    case ($width >= $AllImagesSettingData[3]->value || $height >= $AllImagesSettingData[2]->value):
                        $tuhmbnail = $mediaController->storeThumbnial($Path, $filename, $directory, $filename);
                        $mediumimage = $mediaController->storeMedium($Path, $filename, $directory, $filename);
                        //                $storeLargeImage = $this->Images->Largerecord($filename,$Path,$width,$height);
                        break;
                    case ($width >= $AllImagesSettingData[0]->value || $height >= $AllImagesSettingData[1]->value):
                        $tuhmbnail = $mediaController->storeThumbnial($Path, $filename, $directory, $filename);

                        break;
                }
                $returnimages = DB::table('image_categories')->where('image_id', $image_id)->get();

                //$uploaded_image = DB::table()-where()
                return returnResponse("image is uploaded successfully.", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS,$returnimages);
            } else {
                return response()->json("Please upload a valid image.", HttpStatus::HTTP_BAD_REQUEST);
            }
        } 
         return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);

       
    }
    public function appCommonSettingList(Request $request)
    {
        $consumer_data = getallheaders();
        $consumer_data['consumer_ip'] = $request->ip();
        $consumer_data['consumer_url'] = __FUNCTION__;

        $authenticate = $this->apiAuthenticate($consumer_data);

        if ($authenticate == 1) {
            $language_id = 1;
        if($request->has('language_id')){
            $language_id = $request->language_id;
        }

            $result = DB::table('common_setting_list')
            ->where('language_id', '=', $language_id)
            ->get();

            return returnResponse("Returned common list.", HttpStatus::HTTP_OK, HttpStatus::HTTP_SUCCESS, $result);
        }
        return returnResponse(HttpStatus::$text[HttpStatus::HTTP_UNAUTHORIZED], HttpStatus::HTTP_UNAUTHORIZED);
    }

}
