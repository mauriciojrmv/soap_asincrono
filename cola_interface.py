import tkinter as tk
from tkinter import scrolledtext
from threading import Thread, Event
import time
import subprocess

# Evento global para detener el hilo de procesamiento
stop_event = Event()

class ColaApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Cola de Procesos Manager")

        # Cuadro de texto para los mensajes
        self.log_area = scrolledtext.ScrolledText(root, wrap=tk.WORD, width=60, height=15, font=("Arial", 12))
        self.log_area.pack(padx=10, pady=10)

        # Botón para iniciar el proceso
        self.start_button = tk.Button(root, text="Start Cola", command=self.start_cola, font=("Arial", 14))
        self.start_button.pack(pady=5)

        # Botón para parar el proceso
        self.stop_button = tk.Button(root, text="Stop Cola", command=self.stop_cola, font=("Arial", 14))
        self.stop_button.pack(pady=5)

        self.thread = None

    def log_message(self, message):
        self.log_area.insert(tk.END, f"{message}\n")
        self.log_area.see(tk.END)

    def start_cola(self):
        if self.thread is None or not self.thread.is_alive():
            stop_event.clear()  # Reiniciar el evento de parada
            self.thread = Thread(target=self.run_cola_process)
            self.thread.start()
            self.log_message("Proceso de cola iniciado.")
        else:
            self.log_message("El proceso ya está en ejecución.")

    def stop_cola(self):
        stop_event.set()  # Detener el hilo de procesamiento
        self.log_message("Proceso de cola detenido.")

    def run_cola_process(self):
        # Mantén el ciclo de ejecución hasta que el evento stop_event sea activado
        while not stop_event.is_set():
            # Ejecutar el script PHP para procesar la cola de peticiones
            try:
                result = subprocess.run(['php', 'procesarcola.php'], capture_output=True, text=True)
                self.log_message(result.stdout)
                time.sleep(5)  # Pausa antes de volver a consultar la cola
            except Exception as e:
                self.log_message(f"Error al ejecutar el proceso de cola: {str(e)}")

# Crear la ventana principal de la interfaz
if __name__ == "__main__":
    root = tk.Tk()
    app = ColaApp(root)
    root.mainloop()
