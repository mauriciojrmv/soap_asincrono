import tkinter as tk
from tkinter import scrolledtext
import concurrent.futures
import requests
import random
import time
import xml.etree.ElementTree as ET
import uuid
from threading import Event, Thread

# URL del servidor SOAP en PHP
URL = "http://localhost:8000/colas/server.php"

# Espacios de nombres usados en el XML
NAMESPACES = {
    'SOAP-ENV': 'http://schemas.xmlsoap.org/soap/envelope/',
    'ns1': 'urn:PersonService',
    'xsi': 'http://www.w3.org/2001/XMLSchema-instance',
    'ns2': 'http://xml.apache.org/xml-soap',
    'SOAP-ENC': 'http://schemas.xmlsoap.org/soap/encoding/',
    'xsd': 'http://www.w3.org/2001/XMLSchema'
}

# evento parael cierre de la simulacion
stop_event = Event()


def obtener_info_cuentas():
    soap_body = """
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:PersonService">
       <soapenv:Header/>
       <soapenv:Body>
          <urn:getCuentasInfo/>
       </soapenv:Body>
    </soapenv:Envelope>
    """
    
    headers = {
        'Content-Type': 'text/xml; charset=utf-8',
        'SOAPAction': 'urn:PersonService#getCuentasInfo'
    }
    
    response = requests.post(URL, data=soap_body, headers=headers)
    
    # Imprimir el contenido de la respuesta para inspección
    print(response.status_code)
    print(response.text)
    
    if response.status_code == 200:
        root = ET.fromstring(response.content)
        cuentas_info = {
            'cuentas': [],
            'maxCuenta': None,
            'minCuenta': None
        }

        return_element = root.find('.//ns1:getCuentasInfoResponse/return', NAMESPACES)
        
        for item in return_element.findall('item', NAMESPACES):
            key = item.find('key', NAMESPACES).text
            value = item.find('value', NAMESPACES)
            
            if key == 'cuentas':
                for cuenta_item in value.findall('item', NAMESPACES):
                    cuenta = {}
                    for cuenta_detail in cuenta_item.findall('item', NAMESPACES):
                        cuenta_key = cuenta_detail.find('key', NAMESPACES).text
                        cuenta_value = cuenta_detail.find('value', NAMESPACES).text
                        
                        if cuenta_key == 'id':
                            cuenta['id'] = int(cuenta_value)
                        elif cuenta_key == 'saldo':
                            cuenta['saldo'] = float(cuenta_value)
                    cuentas_info['cuentas'].append(cuenta)
            
            elif key == 'maxCuenta':
                max_cuenta = {}
                for cuenta_detail in value.findall('item', NAMESPACES):
                    cuenta_key = cuenta_detail.find('key', NAMESPACES).text
                    cuenta_value = cuenta_detail.find('value', NAMESPACES).text
                    if cuenta_key == 'id':
                        max_cuenta['id'] = int(cuenta_value)
                    elif cuenta_key == 'saldo':
                        max_cuenta['saldo'] = float(cuenta_value)
                cuentas_info['maxCuenta'] = max_cuenta
            
            elif key == 'minCuenta':
                min_cuenta = {}
                for cuenta_detail in value.findall('item', NAMESPACES):
                    cuenta_key = cuenta_detail.find('key', NAMESPACES).text
                    cuenta_value = cuenta_detail.find('value', NAMESPACES).text
                    if cuenta_key == 'id':
                        min_cuenta['id'] = int(cuenta_value)
                    elif cuenta_key == 'saldo':
                        min_cuenta['saldo'] = float(cuenta_value)
                cuentas_info['minCuenta'] = min_cuenta

        return cuentas_info
    else:
        raise Exception(f"Error obteniendo cuentas: {response.status_code}")


def ejecutar_transaccion(cuenta_id, monto, token, tipo="depositar"):
    # Genera el callback URL dinámicamente o establece una URL fija
    callback_url = f"http://localhost:8000/colas/cliente/deposito.php"  # Este es solo un ejemplo

    # Cuerpo del SOAP que incluye el campo callback_url
    soap_body = f"""
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:PersonService">
       <soapenv:Header/>
       <soapenv:Body>
          <urn:{tipo}>
             <cuenta_id>{cuenta_id}</cuenta_id>
             <monto>{monto}</monto>
             <token>{token}</token>
             <callback_url>{callback_url}</callback_url>  <!-- Incluyendo el callback_url -->
          </urn:{tipo}>
       </soapenv:Body>
    </soapenv:Envelope>
    """
    
    headers = {
        'Content-Type': 'text/xml; charset=utf-8',
        'SOAPAction': f'urn:PersonService#{tipo}'
    }

    # Hacer la petición POST al servidor SOAP
    response = requests.post(URL, data=soap_body, headers=headers)

    if response.status_code == 200:
        return f"Transacción #{token}, {tipo.capitalize()}, {monto:.2f}, Exitoso"
    else:
        return f"Transacción #{token}, {tipo.capitalize()}, {monto:.2f}, Fallido"


def simular_concurrencia_indefinida(num_hilos, cuentas_info, output_widget):
    cuentas = [cuenta for cuenta in cuentas_info['cuentas']]
    transaction_counter = 1

    def registrar_transaccion(resultado):
        nonlocal transaction_counter
        output_widget.insert(tk.END, f"#{transaction_counter}, {resultado}\n")
        output_widget.see(tk.END)
        transaction_counter += 1
    
    def ejecutar_hilo():
        while not stop_event.is_set():
            cuenta = random.choice(cuentas)
            cuenta_id = cuenta['id']
            saldo = cuenta['saldo']

            # elegir de manera aleatoria deposito o retiro
            tipo_transaccion = random.choice(["depositar", "retirar"]) if saldo > 0 else "depositar"
            if tipo_transaccion == "retirar":
                monto = min(saldo, random.uniform(10, 1000))
            else:
                monto = random.uniform(10, 1000)

            token = str(uuid.uuid4())

            # Resultado de cada transaccion
            resultado = ejecutar_transaccion(cuenta_id, monto, token, tipo_transaccion)
            registrar_transaccion(resultado)

            time.sleep(random.uniform(1, 3))  # delay de transacciones

   
    with concurrent.futures.ThreadPoolExecutor(max_workers=num_hilos) as executor:
        futuros = [executor.submit(ejecutar_hilo) for _ in range(num_hilos)]
        concurrent.futures.wait(futuros)


def run_simulation_in_background(num_hilos, cuentas_info):
    simular_concurrencia_indefinida(num_hilos, cuentas_info, output_text)


def iniciar_simulacion():
    global simulation_thread
    stop_event.clear()  
    try:
        num_hilos_str = entry_hilos.get().strip()
        if not num_hilos_str.isdigit() or int(num_hilos_str) <= 0:
            output_text.insert(tk.END, "ERROR - Ingrese un número válido de hilos (número entero positivo).\n")
            return

        num_hilos = int(num_hilos_str)
        cuentas_info = obtener_info_cuentas()

        # Run the simulation in a separate thread to keep the GUI responsive
        simulation_thread = Thread(target=run_simulation_in_background, args=(num_hilos, cuentas_info))
        simulation_thread.start()

    except ValueError as ve:
        output_text.insert(tk.END, "ERROR - Ingrese un número válido de hilos (número entero).\n")
    except Exception as e:
        output_text.insert(tk.END, f"ERROR - {str(e)}\n")


def parar_simulacion():
    global stop_event
    stop_event.set()
    output_text.insert(tk.END, "Simulación detenida.\n")

# Crear la interfaz gráfica con Tkinter
root = tk.Tk()
root.title("Simulación de Transacciones Concurrentes")
root.geometry("600x500")
root.configure(bg="#2c2c2c")  # Modo oscuro

# Etiqueta para la cantidad de hilos
label_hilos = tk.Label(root, text="Ingrese la cantidad de hilos:", bg="#2c2c2c", fg="white", font=("Arial", 12))
label_hilos.pack(pady=10)

# Entrada para hilos
entry_hilos = tk.Entry(root, bg="#1e1e1e", fg="white", font=("Arial", 12))
entry_hilos.pack(pady=10)

# Botón para iniciar simulación
start_button = tk.Button(root, text="Iniciar Simulación", command=iniciar_simulacion, bg="#007bff", fg="white", font=("Arial", 12))
start_button.pack(pady=10)

# Botón para parar simulación
stop_button = tk.Button(root, text="Parar Simulación", command=parar_simulacion, bg="#dc3545", fg="white", font=("Arial", 12))
stop_button.pack(pady=10)

# Área de texto para mostrar los resultados de las transacciones
output_text = scrolledtext.ScrolledText(root, bg="#1e1e1e", fg="white", font=("Arial", 10), height=15, width=70)
output_text.pack(pady=10)

root.mainloop()