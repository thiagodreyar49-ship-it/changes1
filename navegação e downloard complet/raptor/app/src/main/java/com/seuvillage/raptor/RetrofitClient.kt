package com.seuvillage.raptor.network

import com.seuvillage.raptor.data.Command
import com.seuvillage.raptor.data.CommandStatusUpdate
import com.seuvillage.raptor.data.Device
import com.seuvillage.raptor.data.FileItem
import com.seuvillage.raptor.data.FileExplorerRequest
import com.seuvillage.raptor.data.CommandResponse
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.*

object RetrofitClient {
    private const val BASE_URL = "http://192.168.3.175/raptor/api/"

    val instance: ApiService by lazy {
        val retrofit = Retrofit.Builder()
            .baseUrl(BASE_URL)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
        retrofit.create(ApiService::class.java)
    }
}

interface ApiService {
    @POST("register.php")
    suspend fun registerDevice(@Body device: Device): retrofit2.Response<Void>

    @POST("file_explorer.php")
    suspend fun sendFileExplorerResult(@Body request: FileExplorerRequest): retrofit2.Response<Void>

    @GET("command_files.php")
    suspend fun getFileExplorerCommand(@Query("imei") imei: String): retrofit2.Response<CommandResponse>

    @POST("update_command_status.php")
    suspend fun updateCommandStatus(@Body statusUpdate: CommandStatusUpdate): retrofit2.Response<Void>

    // Novo endpoint para upload de arquivos
    @Multipart // Indica que esta requisição é multipart (para envio de arquivos)
    @POST("upload_file_from_device.php")
    suspend fun uploadFile(
        @Part("imei") imei: RequestBody,
        @Part("command_id") commandId: RequestBody,
        @Part("original_path") originalPath: RequestBody,
        @Part file: MultipartBody.Part // O próprio arquivo
    ): retrofit2.Response<Void>
}
