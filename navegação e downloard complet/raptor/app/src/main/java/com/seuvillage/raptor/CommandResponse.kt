package com.seuvillage.raptor.data

import com.google.gson.annotations.SerializedName

data class CommandResponse(
    val status: String,
    val message: String? = null,
    val command: Command? = null // O objeto Command real está aninhado aqui
)
