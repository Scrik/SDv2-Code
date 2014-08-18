<?php

/**
 * Handling the Payment Postprocessing
 * 
 * This Crontroller handles the Postprocessing of the transactions after they have been confirmed by the provider
 * 
 * TODO: Add a warning message to the DB instead of existing directly
 * 
 * @author Werner Maisl
 * @copyright (c) 2014, Werner Maisl
 */
class PaymentQueueWorker
{

    public function fire($job, $data)
    {
        $transaction_id = $data["transaction"];

        Log::info("Queue Worker Fired");
        Log::info("Transaction-ID: " . $transaction_id);

        //Get the transaction from the db
        $transaction = SDPaymentTransaction::find($transaction_id);
        if (!$transaction)
        {
            Log::warning("Could not load Transaction from Database");
            exit();
        }
        if ($transaction->status != "confirmed")
        {
            Log::error("Transaction" . $transaction->id . "not confirmed - aborting");
        }

        Log::info("Transaction loaded from DB");
        Log::info("Transaction user_id: " . $transaction->user_id);
        Log::info("Transaction payment provider: " . $transaction->payment_provider);
        Log::info("Transaction Items: " . $transaction->items);

        //Get the user from the DB
        $user = Sentinel::findById($transaction->user_id);
        if (!$user)
        {
            Log::error("Could not find the user " . $transaction->user_id . " in the DB");
            exit();
        }

        //Get the transaction items
        $trans_items = json_decode($transaction->items);
        if ($trans_items == false)
        {
            Log::error("Invalid Item JSON at the Transaction " . $transaction->id);
            exit();
        }

        //Get the User info from the DB
        $user_infos = DB::table('sd_user_infos')->where('user_id',$user->id)->get();
        if (!$user_infos)
        {
            Log::error("Could not get the User Infos from the db for transaction: " . $transaction->id);
            exit();
        }

        //Go through the items and assign them to the user
        foreach ($trans_items as $trans_item)
        {
            Log::info("Got Trans Item id:" . $trans_item->id);
            Log::info("Got Trans Item count:" . $trans_item->count);

            //Get the item info from the db
            $item = SDItem::find($trans_item->id);
            if (!$item)
            {
                Log::error("Could not get SD Item with id " . $trans_item->id . " from the DB");
                exit();
            }
            Log::info("Got SDItem: " . $item->id . " - " . $item->name_short);

            //Get the handlers for the item and call them
            $handlers = json_decode($item->handlers);
            if ($handlers == false)
            {
                Log::error("Invalid Handler JSON at Item: " . $item->id);
            }

            //Call the item multiple times according to the item count
            for ($i = 1; $i == $trans_item->count; $i++)
            {
                foreach ($handlers as $handler)
                {
                    Log::info("Got Handler Class: " . $handler->class);

                    $item_handler = new $handler->class;

                    $result = $item_handler->add_item($user, $user_infos, $handler->params);
                    
                    if($result == true)
                    {
                        Log::info('Successfully executed '.$handler->class.' for '.$transaction->id);
                        $sd_user_item = new SDUseritem;
                        $sd_user_item->user_id = $user->id;
                        $sd_user_item->item_id = $item->id;
                        $sd_user_item->save();
                    }
                    else
                    {
                        Log::error('Exection of '.$handler->class.' for '.$transaction->id. ' failed');
                    }
                }
            }
        }
        //Update the transaciton
        $transaction->status = "completed";
        $transaction->save();
        
        //Check for errors
        
        
        $job->delete();
    }

}
