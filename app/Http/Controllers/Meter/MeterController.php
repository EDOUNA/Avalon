<?php

namespace App\Http\Controllers\Meter;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Meter\API\MeterAPIController;
use App\Models\Configurations;
use App\Models\Meter\DeviceMeasurementsStats;
use App\Models\Meter\Devices;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class MeterController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function liveUI()
    {
        $configuration = Configurations::where('setting', 'domoitcz_meter_live_ui_interval')->first();
        return view('meter.liveui', ['chartInterval' => $configuration->parameter]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDomoticzData()
    {
        $prodURL = Configurations::where('setting', 'domoticz_meter_prod_url')->first();
        if (null === $prodURL) {
            Log::debug('Could not retrieve the prod URL');
            return response()->json(['status' => 'Missing configuration file'], 500);
        }

        $apiUser = Configurations::where('setting', 'domoticz_meter_api_user')->first();
        if (null === $apiUser) {
            Log::debug('Could not retrieve the basic auth user.');
            return response()->json('Missing API user authentication.', 500);
        }

        $client = new Client();
        try {
            $prodResult = $client->request('GET', $prodURL->parameter, ['auth' => $apiUser->parameter]);
        } catch (\Exception $e) {
            Log::debug('Unable to go to: ' . $this->productionURL . ' Extra data: ' . $e->getMessage());
            return response()->json(['status' => 'Response came in too late or contained an error.'], 500);
        }

        $prodResult = json_decode($prodResult->getBody());

        if (!isset($prodResult->result)) {
            Log::debug('Unable to fetch the results array.');
            return response()->json(['status' => 'Could not find results.'], 500);
        }

        $msg = []; // Blank array for later usage
        foreach ($prodResult->result as $r) {
            if (!isset($r->idx)) {
                Log::debug('Unable to find the IDX attribute.');
                return response()->json(['status' => 'Unable to find the IDX attribute.'], 500);
            }

            // Get the device properties
            $device = new DevicesController();
            $deviceObject = $device->findDeviceIDByIDX($r->idx);

            if (false === $device) {
                Log::debug('Unable to find a proper device for IDX: ' . $r->idx);
                return response()->json(['status' => 'Unable to find a proper device for IDX: ' . $r->idx], 500);
            }

            $counter = explode(' ', $r->CounterToday);
            $counter = $counter[0];

            $msg[$deviceObject->deviceTypes->description] = $counter;

            // Create a new measurement log
            $device->createMeasurement($deviceObject->id, $r);

            // Update/insert a new stat row
            $meterAPI = new MeterAPIController();
            $meterAPI->updateStatsTable($deviceObject->id);
        }

        $time = Carbon::now();
        return response()->json(['status' => 'OK', 'utilities' => $msg, 'serverTime' => $time->toTimeString()]);
    }

    /**
     * @param Int $deviceID
     * @return \Illuminate\Http\JsonResponse
     */
    public function renderDefaultMeasurements(Int $deviceID)
    {
        $configuration = Configurations::where('setting', 'energy_meter_default_total_interval')->first();
        if (null === $configuration) {
            Log::debug('Unable to fetch energy_meter_default_time_interval');
            return response()->json(['status' => 'Unable to fetch configuration.'], 500);
        }

        $device = Devices::with('deviceTypes')->where('id', $deviceID)->first();
        if (null === $device) {
            Log::debug('Could not find deviceID: ' . $deviceID);
            return response()->json(['status' => 'No deviceID found.'], 500);
        }

        $measurements = DeviceMeasurementsStats::with('devices', 'devices.deviceTypes', 'deviceTariffs', 'deviceTariffs.currencies')
            ->where('device_id', $deviceID)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('id')
            ->get()->toArray();

        $output = [];
        foreach ($measurements as $k => $m) {
            // Build the header
            if (!isset($output['deviceDetails'])) {
                $output['deviceDetails']['deviceType'] = $m['devices']['device_types']['description'];
                $output['deviceDetails']['tariff']['amount'] = round($m['device_tariffs']['amount'], 4);
                $output['deviceDetails']['tariff']['symbol'] = $m['device_tariffs']['currencies']['symbol'];
            }

            $output['measurements'][$k]['amount'] = round($m['amount'], 4);
            $output['measurements'][$k]['hour'] = $m['hour'] . ':00';
        }

        return response()->json($output);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function viewStatic()
    {
        $agent = new Agent();
        $configuration = Configurations::where('setting', 'energy_meter_default_timer_interval')->first();
        return view('meter.viewMeasurements', [
            'chartInterval' => $configuration->parameter
            , 'mobileDevice' => $agent->isMobile()
            , 'defaultDimensions' => ['width' => 250, 'height' => 30]
        ]);
    }

    /**
     * @param String $rangeType
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function budget(String $rangeType = 'd')
    {
        $validRangeTypes = ['d', 'w', 'm'];
        if (!in_array($rangeType, $validRangeTypes)) {
            throw new \Exception('Geen geldige range type mee gegeven.');
        }

        $agent = new Agent();
        $configuration = Configurations::where('setting', 'energy_meter_default_timer_interval')->first();
        return view('meter.budgetView', [
            'refreshInterval' => $configuration->parameter
            , 'mobileDevice' => $agent->isMobile()
            , 'defaultDimensions' => ['width' => 200, 'height' => 50]
            , 'rangeType' => $rangeType
        ]);
    }
}
