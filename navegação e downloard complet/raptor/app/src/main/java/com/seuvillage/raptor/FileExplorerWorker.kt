package com.seuvillage.raptor.workers

import android.content.Context
import android.os.Environment // Importação necessária para Environment
import android.util.Log
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.seuvillage.raptor.data.FileItem
import com.seuvillage.raptor.network.RetrofitClient
import com.seuvillage.raptor.data.FileExplorerRequest
import java.io.File

class FileExplorerWorker(appContext: Context, workerParams: WorkerParameters) : CoroutineWorker(appContext, workerParams) {

    private val TAG = "FileExplorerWorker"

    override suspend fun doWork(): Result {
        // Obtenha o caminho do inputData ou use o diretório de armazenamento externo padrão
        // Isso garante que estamos começando de um ponto conhecido do sistema de arquivos
        val initialPath = inputData.getString("path") ?: Environment.getExternalStorageDirectory().absolutePath

        // ATENÇÃO: Substitua "TEST_IMEI_12345" por um método real para obter o IMEI do dispositivo
        // Em um ambiente de produção, o IMEI deve ser obtido de forma segura e persistente.
        val imei = "TEST_IMEI_12345"

        Log.d(TAG, "Iniciando doWork para o caminho: $initialPath com IMEI: $imei")

        val filesList = mutableListOf<FileItem>()
        try {
            val directory = File(initialPath)
            // Verifica se o diretório existe e é um diretório
            if (directory.isDirectory) {
                // Lista os arquivos e subdiretórios dentro do diretório especificado
                directory.listFiles()?.forEach { file ->
                    filesList.add(FileItem(
                        name = file.name,
                        path = file.absolutePath, // Garante que o caminho completo seja salvo
                        isDirectory = file.isDirectory,
                        size = if (file.isDirectory) 0L else file.length()
                    ))
                }
                Log.d(TAG, "Total de arquivos/diretórios encontrados: ${filesList.size} no caminho: $initialPath")
            } else {
                Log.w(TAG, "Caminho não é um diretório ou não existe: $initialPath")
                // Se o caminho não for válido, o Worker falha, mas não tenta novamente (Result.failure)
                return Result.failure()
            }

            // Cria o objeto de requisição que será enviado ao servidor
            val fileExplorerRequest = FileExplorerRequest(imei = imei, files = filesList)

            val apiService = RetrofitClient.instance
            Log.d(TAG, "Tentando enviar resultados para o servidor...")
            // Envia a requisição contendo o IMEI e a lista de arquivos
            val response = apiService.sendFileExplorerResult(fileExplorerRequest)

            if (response.isSuccessful) {
                Log.d(TAG, "Resultados enviados com sucesso! Código de resposta: ${response.code()}")
                // Se a resposta for bem-sucedida, o Worker termina com sucesso
                return Result.success()
            } else {
                val errorBody = response.errorBody()?.string()
                Log.e(TAG, "Falha ao enviar resultados. Código de resposta: ${response.code()}, Mensagem: ${response.message()}, Erro body: $errorBody")
                // Em caso de falha, o Worker tenta novamente mais tarde (Result.retry)
                return Result.retry()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Exceção durante a execução do Worker: ${e.message}", e)
            // Qualquer exceção inesperada resulta em falha do Worker
            return Result.failure()
        }
    }
}
