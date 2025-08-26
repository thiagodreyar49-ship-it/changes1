package com.seuvillage.raptor.data

// FileItem.kt
data class FileItem(
    val name: String,
    val path: String,
    val isDirectory: Boolean,
    val size: Long
)
