package com.seuvillage.raptor.receivers

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import com.seuvillage.raptor.background.FileService // Importação corrigida

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context?, intent: Intent?) {
        if (intent?.action == Intent.ACTION_BOOT_COMPLETED) {
            context?.let {
                val serviceIntent = Intent(it, FileService::class.java)
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    it.startForegroundService(serviceIntent)
                } else {
                    it.startService(serviceIntent)
                }
            }
        }
    }
}
