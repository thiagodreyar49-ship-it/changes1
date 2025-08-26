package com.seuvillage.raptor.data

import com.google.gson.annotations.SerializedName

data class FileExplorerRequest(
    @SerializedName("imei")
    val imei: String,
    @SerializedName("files")
    val files: List<FileItem>
)
