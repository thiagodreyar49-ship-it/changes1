package com.seuvillage.raptor.data

// Command.kt
data class Command(
    val id: Int,
    val deviceId: Int,
    val command: String,
    val status: String,
    val command_data: String? = null // Adiciona a propriedade command_data, pode ser nula
)
