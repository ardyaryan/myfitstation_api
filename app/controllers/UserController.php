<?php

class UserController extends BaseController {

	public function index()
	{
        $loggedIn = Session::get('logged');

        if(isset($loggedIn) && $loggedIn == true) {
            return Redirect::to('user/dashboard');
        }
        return View::make('user/login');
	}

    public function login()
    {
        $email    = Input::get('email');
        $password = Input::get('password');

        $user = Users::where('email', '=', $email)->first();
        //$hashed = Hash::make($password);
        //\Log::info(__METHOD__ . ' ------------------------------ $hashed : ' . print_r($hashed,1));
        if( $user != null && Hash::check($password, $user->password) ) {
            Session::set('logged', true);
            Session::set('email', $email);
            Session::set('user_id', $user->id);

            $result = array('success' => true, 'message' => 'logged in successfully');
        }else {
            Session::flush();
            $result = array('success' => false, 'message' => 'invalid email or password');
        }

        return $result;
    }

    public function logout()
    {
        Session::flush();
        return Redirect::to('/');
    }

    public function newTrips()
    {
        return View::make('admin/newTrips');
    }

    public function signUp()
    {
        return View::make('admin/signup');
    }
    
    public function carDetails($id)
    {
       
        //$carStats = $this->getCarDetails($id);
        $car   = Cars::find($id)->toArray();
        $results = ['carDetails' => $car];
        //\Log::info(__METHOD__.' =======> $carDetails : '.print_r($results, 1));
        
        return View::make('admin/carDetails')->with($results);
        //return View::make('admin/carDetails');
    }

    public function driverDetails($id)
    {

        $driverDetails = $this->getDriverDetails($id);
        $results = ['driverDetails' => $driverDetails['driver_details'], 'trips' => $driverDetails['trips'], 'fuelFillUps' => $driverDetails['fuel_fills']];

        //\Log::info(__METHOD__.' =======> $driverDetails : '.print_r($driverDetails, 1));

        return View::make('admin/driverDetails')->with($results);
    }
    
    public function getCarDetails() {
       
        $to = Input::get('to');
        $from = Input::get('from');
        $carId = Input::get('car_id');
        
        
        if($from == null || $to == null) {
            $today = LocationController::getTime();
            $todayFrom = $today['date'].' 00:00:00';
            $todayTo = $today['date'].' 23:59:59';
        }else {
            $todayFrom = $from.' 00:00:00';
            $todayTo = $to.' 23:59:59';
        }
        
       try{
            $trips = DailyTrips::where('car_id', '=', $carId)->whereBetween('departure_date_time' ,[$todayFrom, $todayTo])->get()->toArray();
        
            $calculatedTrip = [];

            if(!is_null($trips)) {
                
                foreach($trips as $trip) {
               
                    $distance = $trip['arrival_km'] - $trip['departure_km'];
                    $totalTime = strtotime($trip['departure_date_time']) - strtotime($trip['arrival_date_time']);
                    $totalCotst = $trip['extra_charge'] + $trip['extra_cost'];
                    $finalTrip = [
                        'distance'          => $distance,
                        'hours'             => date('H:i:s', $totalTime),
                        'cost'              => $trip['trip_cost'],
                        'parking_toll'      => $totalCotst,
                        'date'              => date("Y-d-m",strtotime($trip['arrival_date_time']))
                    ];
                    array_push($calculatedTrip, $finalTrip);
                }
            }

        } catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
        }
       
       return json_encode($calculatedTrip);
       
    }
    
    public function getFuel() {
       
        $to = Input::get('to');
        $from = Input::get('from');
        $carId = Input::get('car_id');
        
        
        if($from == null || $to == null) {
            $today = LocationController::getTime();
            $todayFrom = $today['date'].' 00:00:00';
            $todayTo = $today['date'].' 23:59:59';
        }else {
            $todayFrom = $from.' 00:00:00';
            $todayTo = $to.' 23:59:59';
        }
       
       try{
            
            $fuelFills = FuelFillUp::where('car_id', '=', $carId)->whereBetween('date_and_time' ,[$todayFrom, $todayTo])->get()->toArray();
            if(!empty($fuelFills)) {
                foreach ($fuelFills as $key => $fuelFill) {
                    \Log::info(__METHOD__.' =======> $fuelFill : '.print_r($fuelFill, 1));
                    $fuelFills[$key]['cost'] = round($fuelFill['cost'], 2);
                    $fuelFills[$key]['amount'] = round($fuelFill['amount'], 2);
                    $fuelFills[$key]['price_per_liter'] = round($fuelFill['price_per_liter'], 2);
                    \Log::info(__METHOD__.' =======> $fuelFill : '.print_r($fuelFill, 1));
                }
            }
            \Log::info(__METHOD__.' =======> $fuelFills : '.print_r($fuelFills, 1));
        } catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
        }
       
       return json_encode($fuelFills);
       
    }

    public function getDriverDetails($id) {

        try{
            $driver   = Driver::find($id)->toArray();
            $trips = DailyTrips::where('user_id', '=', $driver['user_id'])->get()->toArray();
            $fuelFills = FuelFillUp::where('user_id', '=', $driver['user_id'])->get()->toArray();
            $calculatedTrip = [];

            if(!is_null($trips)) {

                foreach($trips as $trip) {

                    $distance = $trip['arrival_km'] - $trip['departure_km'];

                    $finalTrip = [
                        'distance'          => $distance,
                        'cost'              => $trip['trip_cost'],
                        'currency'          => $trip['currency'],
                        'date'              => date("Y-d-m",strtotime($trip['arrival_date_time']))
                    ];
                    array_push($calculatedTrip, $finalTrip);
                }
            }

        } catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
        }

        return ['driver_details' => $driver, 'trips' => $calculatedTrip, 'fuel_fills' => $fuelFills];

    }

    public function createNewUser()
    {
        $post = Input::all();

        $email = $post['email'];

        $user = Users::where('email', '=', $email)->first();

        if($user != null) {
            return array('success' => false, 'message' => 'Email already exists!');
        }

        if($post != null) {
            $password = $post['password'];
            $confirmedPassword = $post['password_2'];

            if($password != $confirmedPassword) {
                $result = array('success' => false, 'message' => 'passwords do not match');
            }else {

                $password = Hash::make($password);

                try{
                    $newUser = new Users();

                    $newUser->first         = $post['first'];
                    $newUser->last          = $post['last'];
                    $newUser->email         = $post['email'];
                    $newUser->password      = $password;
                    $newUser->role_id       = $post['role_id'];
                    $newUser->language_id   = $post['language_id'];
                    $newUser->time_zone     = $post['time_zone'];

                    $newUser->save();

                    if($post['role_id'] == Roles::DRIVER_ROLE_ID) {

                        $newDriver = new Driver();

                        $newDriver->user_id       = $newUser->id;
                        $newDriver->code          = $post['driver_code'];
                        $newDriver->first         = $post['first'];
                        $newDriver->last          = $post['last'];
                        $newDriver->gsm_number    = $post['gsm'];

                        $newDriver->save();
                    }

                    $result = array('success' => true, 'message' => 'New user saved successfully');

                }catch(Exception $ex) {
                    \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
                    $result = array('success' => false, 'message' => 'en error occurred');
                }
            }

            return $result;
        }
    }

    public function viewTrips()
    {
        if(!Session::get('logged')) {
            return Redirect::to('/');
        }
        return View::make('admin/viewTrips');
    }

    public function getTrips()
    {
        //\Log::info(__METHOD__.' +++++++++++++++++++ : '.print_r('here',1));
        $to = Input::get('to');
        $from = Input::get('from');

        if($from == null || $to == null) {
            $today = LocationController::getTime();
            $todayFrom = $today['date'].' 00:00:00';
            $todayTo = $today['date'].' 23:59:59';
        }else {
            $todayFrom = $from.' 00:00:00';
            $todayTo = $to.' 23:59:59';
        }

        try{
            $trips = DailyTrips::where('departure_date_time','>', $todayFrom)
                ->where('departure_date_time','<', $todayTo)
                ->orderBy('departure_date_time', 'desc')->get();

            $results = [];

            if(!is_null($trips)) {
                foreach($trips as $trip) {
                    $driverId = $trip->user_id;

                    $carId    = $trip->car_id;
                    $clientId = $trip->client_id;
                    $distance = $trip->arrival_km - $trip->departure_km;

                    $driver = Driver::where('user_id', '=', $driverId)->first();
                    $car    = Cars::find($carId);
                    $client = Client::find($clientId);
                    
                    $finalTrip = [
                        'trip_id'           => $trip->id,
                        'driver'            => $driver->first.' '.$driver->last,
                        'car'               => $car->name,
                        'client'            => $client->name,
                        'customer'          => $trip->customer_name,
                        'customer_email'    => $trip->customer_email,
                        'customer_phone'    => $trip->customer_phone,
                        'departure_time'    => date("H:i:s",strtotime($trip->departure_date_time)),
                        'arrival_time'      => date("H:i:s",strtotime($trip->arrival_date_time)),
                        'departure_address' => $trip->departure_address,
                        'arrival_address'   => $trip->arrival_address,
                        'departure_km'      => $trip->departure_km,
                        'arrival_km'        => $trip->arrival_km,
                        'distance'          => $distance,
                        'trip_time'         => $trip->trip_time,
                        'extra_charge'      => $trip->extra_charge,//for company
                        'extra_cost'        => $trip->extra_cost,//for company
                        'cost'              => $trip->trip_cost,//.' '.$trip->currency,
                        'date'              => date("Y-d-m",strtotime($trip->arrival_date_time)),
                        'delete'            => $trip->delete_req,
                        'edit'              => $trip->edit_req
                    ];
                    array_push($results, $finalTrip);
                    
                }
            }

        } catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
        }

        return json_encode($results);
    }
    
    public function getDriverDailyTrips()
    {
        $toDay = Input::get('day');
        $from = Input::get('from');
        $to = Input::get('to');
        $page = Input::get('page');
        
        if ($page == 'dashboard') {
            if($from == null || $to == null) {
            $today = LocationController::getTime();
            $todayFrom = $today['date'].' 00:00:00';
            $todayFrom = (date('Y-m-d', strtotime('-30 day'))).' 00:00:00';
            $todayTo = $today['date'].' 23:59:59';
        }else {
            $todayFrom = $from.' 00:00:00';
            $todayTo = $to.' 23:59:59';
        }
        }else {
            if($toDay == null) {
            $today = LocationController::getTime();
            $todayFrom = $today['date'].' 00:00:00';
            $todayTo = $today['date'].' 23:59:59';
        }else {
            $todayFrom = $toDay.' 00:00:00';
            $todayTo = $toDay.' 23:59:59';
            }
        }
      
        //\Log::info(__METHOD__.' +++++++++++++++++++ : '.print_r('here',1));
        try{
            
            $allDrivers = Driver::all()->toArray();
            
            $driverBreakdown = [];
            foreach ($allDrivers as $driver) {
                $userId = $driver['user_id'];
                $query = DB::select(DB::raw('SELECT * FROM (SELECT departure_date_time, MIN(departure_km) AS start_km FROM daily_trips WHERE user_id = ' . $userId . ' AND departure_date_time BETWEEN "' . $todayFrom . '" AND "' .$todayTo . '") AS a,
                        (SELECT  COUNT(id) AS count, MAX(arrival_km) AS end_km FROM daily_trips WHERE user_id = ' . $userId . ' AND departure_date_time BETWEEN "' . $todayFrom . '" AND "' .$todayTo . '") AS b,
                        (SELECT  MIN(departure_date_time) AS start_time FROM daily_trips WHERE user_id = ' . $userId . ' AND departure_date_time BETWEEN "' . $todayFrom . '" AND "' .$todayTo . '") AS c,
                        (SELECT  MAX(departure_date_time) AS end_time FROM daily_trips WHERE user_id = ' . $userId . ' AND departure_date_time BETWEEN "' . $todayFrom . '" AND "' .$todayTo . '") AS d,
                        (SELECT  SUM(trip_distance) AS total_trip_km FROM daily_trips WHERE user_id = ' . $userId . ' AND departure_date_time BETWEEN "' . $todayFrom . '" AND "' .$todayTo . '") AS e,
                        (SELECT  SUM(trip_time) AS total_trip_time FROM daily_trips WHERE user_id = ' . $userId . ' AND departure_date_time BETWEEN "' . $todayFrom . '" AND "' .$todayTo . '") AS f,
                        (SELECT GROUP_CONCAT(car_id) AS car_ids FROM (SELECT car_id FROM daily_trips WHERE user_id = ' . $userId . ' AND departure_date_time BETWEEN "' . $todayFrom . '" AND "' .$todayTo . '" GROUP BY car_id) AS g) as h
                '));
                $dailyBreakdown = (array) $query[0];
                $dailyBreakdown['id'] = $driver['id'];
                $dailyBreakdown['driver_name'] = $driver['first'] . ' ' . $driver['last'];
                
                $dailyBreakdown['total_km'] = $dailyBreakdown['end_km'] - $dailyBreakdown['start_km'];
                $dailyBreakdown['free_ride_km'] = $dailyBreakdown['total_km'] - $dailyBreakdown['total_trip_km'];
                if($dailyBreakdown['total_km'] != 0) {
                    $dailyBreakdown['free_ride_km_percent'] = round( ($dailyBreakdown['free_ride_km']/ $dailyBreakdown['total_km']) * 100, 2);
                    $dailyBreakdown['total_trip_km_percent'] = round( ($dailyBreakdown['total_trip_km']/ $dailyBreakdown['total_km']) * 100, 2);
                } else {
                    $dailyBreakdown['free_ride_km_percent'] = 0;
                    $dailyBreakdown['total_trip_km_percent'] = 0;
                }
                
                $startTime = strtotime($dailyBreakdown['start_time']);
                $endTime = strtotime($dailyBreakdown['end_time']);
                $totalMinutes = round(($endTime - $startTime) / 60); //in minutes
                $totalWorkMinutes = $dailyBreakdown['total_trip_time']; // in minutes
                $totalFreeMinutes = $totalMinutes - $totalWorkMinutes;
                if ($dailyBreakdown['count'] != 0) {
                    $hoursPerTrip = $totalWorkMinutes / $dailyBreakdown['count'];
                    $kmPerTrip = round( $dailyBreakdown['total_km'] / $dailyBreakdown['count']);
                } else {
                    $hoursPerTrip = 0;
                    $kmPerTrip = 0;
                }
                
                if ($totalMinutes != 0) {
                    $totalWorkPercentage = round( ($totalWorkMinutes / $totalMinutes) * 100, 2 );
                    $totalFreePercentage = round( ($totalFreeMinutes / $totalMinutes) * 100, 2 );
                } else {
                    $totalWorkPercentage = 0;
                    $totalFreePercentage = 0;
                }
                
                $dailyBreakdown['total_hours'] = date('H:i:s', $totalMinutes);
                $dailyBreakdown['total_work_hours'] = date('H:i:s', $totalWorkMinutes);
                $dailyBreakdown['total_free_hours'] = date('H:i:s', $totalFreeMinutes);
                $dailyBreakdown['total_free_hours_percent'] = $totalFreePercentage;
                $dailyBreakdown['total_work_hours_percent'] = $totalWorkPercentage;
                $dailyBreakdown['hours_per_trip'] = date('H:i:s', $hoursPerTrip);
                $dailyBreakdown['km_per_trip'] = $kmPerTrip;
                
                $dailyBreakdown['receipt'] = 0;
              
                if ($dailyBreakdown['car_ids'] != null) {
                    $carNames = '';
                    $carIds = explode(',', $dailyBreakdown['car_ids']);
                    foreach($carIds as $carId) {
                        $car = Cars::find($carId)->toArray();
                        $carNames .=$car['name'] .', ';
                    }
                    $dailyBreakdown['cars'] = $carNames;
                } else {
                    $dailyBreakdown['cars'] = 'NA';
                }
                unset ($dailyBreakdown['car_ids']);
                array_push($driverBreakdown, $dailyBreakdown);
                
            }

            \Log::info(__METHOD__.' +++++++++++++++++++ $driverBreakdown: '.print_r($driverBreakdown,1));

        } catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
        }

        return json_encode($driverBreakdown);
    }

    public function viewDashboard()
    {

        return View::make('user/dashboard');
    }

    public function viewDrivers()
    {
        return View::make('admin/viewDrivers');
    }

    public function viewClients()
    {
        return View::make('admin/viewClients');
    }

    public function viewPayments()
    {
        return View::make('admin/viewPayments');
    }

    public function getDrivers()
    {
        $results = Driver::all()->toArray();

        foreach ($results as $key => $driver) {
            $driverName = $driver['first'].' '.$driver['last'];
            $email = Users::where('id', '=', $driver['user_id'])->pluck('email');
            $hours = Driver::getHoursByDriverId($driver['user_id']);
            $trips = Driver::getTripsByDriverId($driver['user_id']);
            $earning = Driver::getEarningsByDriverId($driver['user_id']);

            $results[$key]['hours'] =  $hours['hours'];
            $results[$key]['trips'] =  $trips['count'];
            $results[$key]['earning'] =  $earning['earning'];//.' '.$earning['currency'];
            $results[$key]['hour_per_trip'] =  round(($trips['count'] == 0) ? 0 : $hours['hours'] / $trips['count'], 2);
            $results[$key]['earning_per_hour'] =  round(($hours['hours'] == 0) ? 0 : $earning['earning']/$hours['hours'], 2).' MAD';
            $results[$key]['earning_per_trip'] =  round(($trips['count'] == 0) ? 0 : $earning['earning']/$trips['count'], 2).' MAD';
            $results[$key]['name'] = $driverName;
            $results[$key]['email'] = $email;
            unset($results[$key]['first']);
            unset($results[$key]['last']);
        }
        //Log::info(__METHOD__.print_r($results, 1));
        return json_encode($results);
    }

    public function getPayments()
    {
        $to = Input::get('to');
        $from = Input::get('from');

        if($from == null || $to == null) {
            $today = LocationController::getTime();
            $todayFrom = $today['date'].' 00:00:00';
            $todayTo = $today['date'].' 23:59:59';
        }else {
            $todayFrom = $from.' 00:00:00';
            $todayTo = $to.' 23:59:59';
        }

        try{
            $results = Payment::where('created_at','>', $todayFrom)
                ->where('created_at','<', $todayTo)
                //->orderBy('created_at')
                ->get()
                ->toArray();

            if(!is_null($results)) {
                foreach ($results as $key => $payment) {

                    $driver = Driver::where('id','=', $payment['driver_id'])->first()->toArray();
                    $currency = Currency::find($payment['currency'])->pluck('currency');
                    $results[$key]['driver_name'] =  $driver['first'].' '.$driver['last'];
                    $results[$key]['currency'] =  $currency;
                }
            }

            $queries = DB::getQueryLog();
            $last_query = end($queries);

        } catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
        }
        //\Log::info(__METHOD__.print_r($results, 1));
        return json_encode($results);
    }

    public function getClients()
    {
        $clients = Client::all()->toArray();
        foreach ($clients as $key => $client) {
            $clients[$key]['price_per_km'] = round($client['price_per_km'], 3);
            $clients[$key]['price_per_min'] = round($client['price_per_km'], 3);
            $clients[$key]['us_dollar_exchange_rate'] = round($client['us_dollar_exchange_rate'], 3);
        }

        return json_encode($clients);
    }

    public function viewCars()
    {
        return View::make('admin/viewCars');
    }

    public function getCars()
    {
        $results = Cars::all()->toArray();

        return json_encode($results);
    }

    public function getTripsByDriver() {

        $to = Input::get('to');
        $from = Input::get('from');

        if($from == null || $to == null) {
            $todayFrom = (date('Y-m-d', strtotime('-30 day'))).' 00:00:00';
            $todayTo = date('Y-m-d').' 23:59:59';
        }else {
            $todayFrom = $from.' 00:00:00';
            $todayTo = $to.' 23:59:59';
        }

        $results = [];
        try{

            $trips = DB::table('daily_trips')
                ->select(DB::raw('*, count(id) as count'))
                ->where('departure_date_time','>', $todayFrom)
                ->where('departure_date_time','<', $todayTo)
                ->groupBy(DB::raw('DATE_FORMAT(arrival_date_time, "%Y/%d/%m")'))//)'arrival_date_time')
                ->orderBy('departure_date_time')
                ->get();

            if(!is_null($trips)) {
                foreach ($trips as $trip) {
                    $coordinates = [];
                    $coordinates['x'] = date('Y-m-d', strtotime($trip->arrival_date_time));
                    $coordinates['y'] = intval($trip->count);
                    //$coordinates['name'] = 'Driver Id: '.$trip->user_id;
                    array_push($results, $coordinates);
                }
            }
            /*
            $queries = DB::getQueryLog();
            $last_query = end($queries);
            \Log::info(__METHOD__.' | ===================== Last Query: '.print_r($queries, 1));
            */
        } catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
        }
        //\Log::info(__METHOD__.' | =====> $results : '.print_r($results,1 ));

        return $results;
    }

    public function getDriverById() {

        $driverId = Input::get('driver_id');

        try {
            $driver = Driver::find($driverId);
            $email = Users::where('id', '=', $driver->user_id)->pluck('email');
            $driver->email = $email;
            $result = array('success' => true, 'driver' => $driver);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false, 'driver' => null);
        }
        return $result;
    }

    public function getClientById() {

        $clientId = Input::get('client_id');

        try {
            $client = Client::find($clientId);
            $result = array('success' => true, 'client' => $client);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false, 'driver' => null);
        }
        return $result;
    }

    public function saveDriver() {

        $driverId = Input::get('driver_id');
        $code     = Input::get('code');
        $first = Input::get('first');
        $last = Input::get('last');
        $email = Input::get('email');
        $gsmNumber = Input::get('gsm_number');
        $carId = Input::get('car_id');

        try {
            $driver = Driver::find($driverId);

            $driver->code = $code;
            $driver->first = $first;
            $driver->last = $last;
            $driver->gsm_number = $gsmNumber;
            $driver->car_id = $carId;

            $driver->save();

            $user = Users::find($driver->user_id);
            $user->email = $email;
            $user->save();

            $result = array('success' => true);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;
    }

    public function deleteTrip()
    {
        $tripId = Input::get('trip_id');

        try {
            $myTrip = DailyTrips::find($tripId);
            $myTrip->delete();

            $results = array('success' => true, 'message' => 'deletion requested');

        }catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
            $results = array('success' => false, 'message' => 'an error occurred');
        }
        return $results;
    }

    public function saveNewDriver() {

        $code     = Input::get('code');
        $first = Input::get('first');
        $last = Input::get('last');
        $email = Input::get('email');
        $password = Input::get('password');
        $gsmNumber = Input::get('gsm_number');
        $carId = Input::get('car_id');
        $timeZone = Input::get('time_zone');
        $languageId = Input::get('language_id');

        try {
            $user = new Users;

            $user->first = $first;
            $user->last  = $last;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->role_id = Roles::DRIVER_ROLE_ID;
            $user->language_id = $languageId;
            $user->time_zone = $timeZone;
            $user->save();

            $driver = new Driver;

            $driver->user_id = $user->id;
            $driver->code = $code;
            $driver->first = $first;
            $driver->last = $last;
            $driver->gsm_number = $gsmNumber;
            $driver->car_id = $carId;

            $driver->save();

            $result = array('success' => true);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;
    }

    public function deleteDriver()
    {
        $driverId = Input::get('driver_id');

        try {
            $driver = Driver::find($driverId);
            $userId = $driver->user_id;
            $driver->delete();

            $user = Users::find($userId)->delete();

            $results = array('success' => true, 'message' => 'deletion requested');

        }catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
            $results = array('success' => false, 'message' => 'an error occurred');
        }
        return $results;
    }

    public function saveNewCar() {

        $name     = Input::get('name');
        $brand = Input::get('brand');
        $model = Input::get('model');
        $registration = Input::get('registration');
        $policeNumber = Input::get('police_number');
        $uberNumber = Input::get('uber_number');

        try {
            $user = new Cars;

            $user->name = $name;
            $user->brand  = $brand;
            $user->model = $model;
            $user->registration = $registration;
            $user->police_number = $policeNumber;
            $user->uber_number = $uberNumber;
            $user->save();

            $result = array('success' => true);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;
    }

    public function deleteCar()
    {
        $carId = Input::get('car_id');

        try {
            $car = Cars::find($carId);
            $car->delete();

            $results = array('success' => true, 'message' => 'deletion done');

        }catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
            $results = array('success' => false, 'message' => 'an error occurred');
        }
        return $results;
    }

    public function getCarById() {

        $carId = Input::get('car_id');

        try {
            $car = Cars::find($carId);

            $result = array('success' => true, 'car' => $car);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false, 'car' => null);
        }
        return $result;
    }

    public function saveCar() {

        $carId = Input::get('car_id');
        $name     = Input::get('name');
        $brand = Input::get('brand');
        $model = Input::get('model');
        $registration = Input::get('registration');
        $policeNumber = Input::get('police_number');
        $uberNumber = Input::get('uber_number');

        try {
            $car = Cars::find($carId);

            $car->name = $name;
            $car->brand = $brand;
            $car->model = $model;
            $car->registration = $registration;
            $car->police_number = $policeNumber;
            $car->uber_number = $uberNumber;

            $car->save();

            $result = array('success' => true);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;
    }

    public function savePayment() {

        $amount     = Input::get('amount');
        $other      = Input::get('other');
        $currencyId = Input::get('currency');
        $driverID   = Input::get('driver');

        try {
            $payment = new Payment;
            $payment->amount = $amount;
            $payment->other = $other;
            $payment->currency = $currencyId;
            $payment->driver_id = $driverID;

            $payment->save();

            $result = array('success' => true);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;

    }


    public function getEditedTripById() {
        $tripId = Input::get('trip_id');

        try {

            $originalTrip = DailyTrips::find($tripId)->toArray();
            $editedTrip   = DailyTripsRevision::where('trip_id', '=', $tripId)->first()->toArray();
            //\Log::info($editedTrip);

            $trip = array ('original_trip' => $originalTrip, 'edited_trip' => $editedTrip);

            $result = array('success' => true, 'trip' => $trip);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;
    }

    public function saveEditedTrip() {
        $tripId = Input::get('trip_id');
        $edited_customer_name = Input::get('edited_customer_name');
        $edited_customer_email = Input::get('edited_customer_email');
        $edited_customer_phone = Input::get('edited_customer_phone');
        $edited_start_km = Input::get('edited_start_km');
        $edited_end_km = Input::get('edited_end_km');
        $edited_start_time = Input::get('edited_start_time');
        $edited_end_time = Input::get('edited_end_time');
        $edited_departure_address = Input::get('edited_departure_address');
        $edited_destination_address = Input::get('edited_destination_address');


        try {

            $originalTrip = DailyTrips::find($tripId);

            $originalTrip->customer_name = $edited_customer_name;
            $originalTrip->customer_email = $edited_customer_email;
            $originalTrip->customer_phone = $edited_customer_phone;
            $originalTrip->departure_km = $edited_start_km;
            $originalTrip->arrival_km = $edited_end_km;
            $originalTrip->departure_date_time = $edited_start_time;
            $originalTrip->arrival_date_time = $edited_end_time;
            $originalTrip->departure_address = $edited_departure_address;
            $originalTrip->arrival_address = $edited_destination_address;
            $originalTrip->edit_req = null;
            $originalTrip->save();

            DailyTripsRevision::where('trip_id', '=', $tripId)->first()->delete();

            $result = array('success' => true);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;
    }

    public function saveNewClient() {

        $name = Input::get('name');
        $base = Input::get('base');
        $newPricePerKm = Input::get('price_per_km');
        $newPricePerMin = Input::get('price_per_min');
        $currency = Input::get('currency');
        $exchangeRate = Input::get('us_dollar_exchange_rate');

        try {
            $client = new Client();

            $client->name = $name;
            $client->base  = $base;
            $client->price_per_km = $newPricePerKm;
            $client->price_per_min = $newPricePerMin;
            $client->currency = $currency;
            $client->us_dollar_exchange_rate = $exchangeRate;

            $client->save();

            $result = array('success' => true);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;
    }

    public function saveClient() {

        $clientId = Input::get('client_id');
        $name = Input::get('name');
        $base = Input::get('base');
        $newPricePerKm = Input::get('price_per_km');
        $newPricePerMin = Input::get('price_per_min');
        $currency = Input::get('currency');
        $exchangeRate = Input::get('us_dollar_exchange_rate');

        try {
            $client = Client::find($clientId);

            $client->name = $name;
            $client->base  = $base;
            $client->price_per_km = $newPricePerKm;
            $client->price_per_min = $newPricePerMin;
            $client->currency = $currency;
            $client->us_dollar_exchange_rate = $exchangeRate;

            $client->save();

            $result = array('success' => true);

        }catch(Exception $ex) {
            \Log::error(__METHOD__ . ' | error :' . print_r($ex, 1));
            $result = array('success' => false);
        }
        return $result;
    }

    public function deleteClient()
    {
        $clientId = Input::get('client_id');

        try {
            $client = Client::find($clientId);

            $client->delete();

            $results = array('success' => true, 'message' => 'deletion successful');

        }catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
            $results = array('success' => false, 'message' => 'an error occurred');
        }
        return $results;
    }

    public function createReport()
    {
        $to = Input::get('to');
        $from = Input::get('from');

        if($from == null || $to == null) {
            $todayFrom = (date('Y-m-d', strtotime('-30 day'))).' 00:00:00';
            $todayTo = date('Y-m-d').' 23:59:59';
        }else {
            $todayFrom = $from.' 00:00:00';
            $todayTo = $to.' 23:59:59';
        }

        $results = [];

        try{

            $trips = DailyTrips::where('departure_date_time','>', $todayFrom)
                ->where('departure_date_time','<', $todayTo)
                ->orderBy('departure_date_time')
                ->get();
            $totalTripCount = count($trips);
            $totalTripCost = 0;
            $totalTripDistance = 0;
            $totalTripTime = 0;
            foreach($trips as $trip) {
                $totalTripCost += $trip->trip_cost;
                $totalTripDistance += ($trip->arrival_km - $trip->departure_km);
                $totalTripTime += (strtotime($trip->arrival_date_time) - strtotime($trip->departure_date_time));
            }
            $totalTripTime = date('H:i:s', $totalTripTime);

            $totalFuel = FuelFillUp::where('date_and_time','>', $todayFrom)
                ->where('date_and_time','<', $todayTo)
                ->orderBy('date_and_time')
                ->get();
            $totalFuelCost = 0;
            $totalFuelAmount = 0;
            foreach($totalFuel as $Fuel) {
                $totalFuelCost += $Fuel->cost;
                $totalFuelAmount += $Fuel->amount;
            }

            $totalPayments = 0;
            $totalOther = 0;
            $payments = Payment::where('created_at','>', $todayFrom)
                ->where('created_at','<', $todayTo)
                ->orderBy('created_at')
                ->get();
            foreach($payments as $payment) {
                $totalPayments += $payment->amount;
                $totalOther += $payment->other;
            }

            $report = ['totalTripCounts' => $totalTripCount,
                        'totalTripCost'   => $totalTripCost,
                        'totalTripkm'     => $totalTripDistance,
                        'totalTripTime'   => $totalTripTime,
                        'totalFuelCost'   => round($totalFuelCost, 2),
                        'totalFuelAmount' => round($totalFuelAmount, 2),
                        'totalPayments'   => $totalPayments,
                        'totalOther'      => $totalOther
            ];
            array_push($results, $report);
            /*
            $queries = DB::getQueryLog();
            $last_query = end($queries);
            */
        } catch(Exception $ex){
            \Log::error(__METHOD__.' | error :'.print_r($ex, 1));
        }
        //\Log::info(__METHOD__.' | =====> $results : '.print_r($results,1 ));

        return json_encode($results);

    }
}
