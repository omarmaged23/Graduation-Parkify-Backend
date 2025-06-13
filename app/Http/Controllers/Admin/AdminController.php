<?php

namespace App\Http\Controllers\Admin;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function changeAdminStatus(Request $request,$id){
        $request->validate([
            'status'=>['required','in:0,1']
        ]);
        try {
            $auth = auth('admin')->user();
            if ($auth->role == 'admin'){
                return response()->json(['error'=>'admin unauthorized to perform this action'],403);
            }
            $admin = Admin::find($id);
            if (!$admin) {
                return response()->json(['error'=>'admin not found'],404);
            }
            if($admin->id == $auth->id){
                return response()->json(['error'=>'cannot change personal account status'],422);
            }
            if($admin->role == 'owner'){
                return response()->json(['error'=>'can not change owner account status'],403);
            }
            if ($admin->status == $request->status) {
                return response()->json(['error'=>"status is already $admin->status"],422);
            }
            if(!($auth->role == 'owner' || ($auth->role == 'super_admin' && $admin->role != 'super_admin'))){
                return response()->json(['error'=>'can not change account status'],422);
            }
            $operation = $admin->update([
                'status' => $request->status
            ]);
            if(!$operation){
                return response()->json(['error'=>'something went wrong'],422);
            }
            event(new AdminActionPerformed($auth->name,$auth->email,"Changed admin $admin->email status to $request->status",$auth->role));
            return response()->json(['success'=>'admin status updated successfully'],200);
        } catch (\Exception $e) {
            return response()->json(['error'=>'something went wrong '.$e->getMessage()],422);
        }

    }
    public function getActivityLog()
    {
        $logs = ActivityLog::all();
        if(!$logs){
            return response()->json(['error' => 'No activity logs found.'], 404);
        }
        return response()->json(['success' => $logs],200);
    }
}
