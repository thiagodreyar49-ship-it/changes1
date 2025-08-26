package com.seuvillage.raptor.workers

import android.content.Context
import android.util.Log
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.seuvillage.raptor.data.CommandStatusUpdate
import com.seuvillage.raptor.network.RetrofitClient
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File
import java.io.IOException

class UploadFileWorker(appContext: Context, workerParams: WorkerParameters) : CoroutineWorker(appContext, workerParams) {

    private val TAG = "UploadFileWorker"

    override suspend fun doWork(): Result {
        val imei = inputData.getString("imei")
        val commandId = inputData.getInt("command_id", -1)
        val filePath = inputData.getString("file_path")

        if (imei == null || commandId == -1 || filePath == null) {
            Log.e(TAG, "Dados de entrada inválidos para UploadFileWorker. IMEI: $imei, Command ID: $commandId, File Path: $filePath")
            return Result.failure()
        }

        val fileToUpload = File(filePath)

        if (!fileToUpload.exists() || !fileToUpload.isFile) {
            Log.e(TAG, "Arquivo não encontrado ou não é um arquivo: $filePath")
            updateCommandStatus(commandId, "failed", "Arquivo não encontrado no dispositivo.")
            return Result.failure()
        }

        try {
            val apiService = RetrofitClient.instance

            // Cria o RequestBody para os campos do formulário
            val imeiBody = imei.toRequestBody("text/plain".toMediaTypeOrNull())
            val commandIdBody = commandId.toString().toRequestBody("text/plain".toMediaTypeOrNull())
            val originalPathBody = filePath.toRequestBody("text/plain".toMediaTypeOrNull())

            // Cria o MultipartBody.Part para o arquivo
            val requestFile = fileToUpload.asRequestBody("application/octet-stream".toMediaTypeOrNull())
            val filePart = MultipartBody.Part.createFormData("file", fileToUpload.name, requestFile)

            Log.d(TAG, "Iniciando upload de ${fileToUpload.name} para o servidor...")
            val response = apiService.uploadFile(imeiBody, commandIdBody, originalPathBody, filePart)

            if (response.isSuccessful) {
                Log.d(TAG, "Arquivo ${fileToUpload.name} enviado com sucesso! Código: ${response.code()}")
                updateCommandStatus(commandId, "completed", "Upload concluído com sucesso.")
                return Result.success()
            } else {
                val errorBody = response.errorBody()?.string()
                Log.e(TAG, "Falha ao enviar arquivo ${fileToUpload.name}. Código: ${response.code()}, Mensagem: ${response.message()}, Erro body: $errorBody")
                updateCommandStatus(commandId, "failed", "Falha no upload: ${response.message()}")
                return Result.retry() // Tenta novamente em caso de falha de rede ou servidor
            }
        } catch (e: IOException) {
            Log.e(TAG, "Erro de IO ao fazer upload do arquivo: ${e.message}", e)
            updateCommandStatus(commandId, "failed", "Erro de rede/IO durante o upload.")
            return Result.retry()
        } catch (e: Exception) {
            Log.e(TAG, "Exceção durante o upload do arquivo: ${e.message}", e)
            updateCommandStatus(commandId, "failed", "Erro inesperado durante o upload.")
            return Result.failure()
        }
    }

    // Função para atualizar o status do comando no servidor
    private suspend fun updateCommandStatus(commandId: Int, status: String, message: String? = null) {
        try {
            val apiService = RetrofitClient.instance
            val statusUpdate = CommandStatusUpdate(commandId, status)
            // Futuramente, você pode adicionar o 'message' ao CommandStatusUpdate se o backend suportar.
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
