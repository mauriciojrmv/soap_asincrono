import tkinter as tk
from tkinter import scrolledtext
import requests
import random
import time
import xml.etree.ElementTree as ET
import uuid
from threading import Event, Thread

# URL of the SOAP server in PHP
URL = "http://localhost:8000/colas/server.php"

# Event to stop the simulation
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
    print("Response status code:", response.status_code)
    print("Response content:", response.content.decode('utf-8'))

    if response.status_code == 200:
        root = ET.fromstring(response.content)
        cuentas_info = {
            'cuentas': [],
            'maxCuenta': None,
            'minCuenta': None
        }

        # Adjust namespaces
        ns = {
            'SOAP-ENV': 'http://schemas.xmlsoap.org/soap/envelope/',
            'ns1': 'urn:PersonService',
            'xsi': 'http://www.w3.org/2001/XMLSchema-instance',
            'ns2': 'http://xml.apache.org/xml-soap',
            'SOAP-ENC': 'http://schemas.xmlsoap.org/soap/encoding/',
            'xsd': 'http://www.w3.org/2001/XMLSchema'
        }

        # Check for SOAP Fault
        fault_element = root.find('.//SOAP-ENV:Fault', ns)
        if fault_element is not None:
            fault_string = fault_element.find('faultstring').text
            raise Exception(f"SOAP Fault: {fault_string}")

        # Find the return element
        return_element = root.find('.//ns1:getCuentasInfoResponse/return', ns)
        if return_element is None:
            return_element = root.find('.//getCuentasInfoResponse/return', ns)
        if return_element is None:
            raise Exception("No return element found in response.")

        # Now parse the return value
        for item in return_element.findall('item', ns):
            key_element = item.find('key', ns)
            value_element = item.find('value', ns)
            if key_element is not None and value_element is not None:
                key = key_element.text
                if key == 'cuentas':
                    cuentas_array = []
                    for cuenta_item in value_element.findall('item', ns):
                        cuenta = {}
                        for cuenta_detail in cuenta_item.findall('item', ns):
                            cuenta_key_element = cuenta_detail.find('key', ns)
                            cuenta_value_element = cuenta_detail.find('value', ns)
                            if cuenta_key_element is not None and cuenta_value_element is not None:
                                cuenta_key = cuenta_key_element.text
                                cuenta_value = cuenta_value_element.text
                                cuenta[cuenta_key] = cuenta_value
                        if cuenta:  # Only append non-empty cuentas
                            cuentas_array.append(cuenta)
                    cuentas_info['cuentas'] = cuentas_array
                elif key == 'maxCuenta':
                    maxCuenta = {}
                    for cuenta_detail in value_element.findall('item', ns):
                        cuenta_key_element = cuenta_detail.find('key', ns)
                        cuenta_value_element = cuenta_detail.find('value', ns)
                        if cuenta_key_element is not None and cuenta_value_element is not None:
                            cuenta_key = cuenta_key_element.text
                            cuenta_value = cuenta_value_element.text
                            maxCuenta[cuenta_key] = cuenta_value
                    if maxCuenta:
                        cuentas_info['maxCuenta'] = maxCuenta
                elif key == 'minCuenta':
                    minCuenta = {}
                    for cuenta_detail in value_element.findall('item', ns):
                        cuenta_key_element = cuenta_detail.find('key', ns)
                        cuenta_value_element = cuenta_detail.find('value', ns)
                        if cuenta_key_element is not None and cuenta_value_element is not None:
                            cuenta_key = cuenta_key_element.text
                            cuenta_value = cuenta_value_element.text
                            minCuenta[cuenta_key] = cuenta_value
                    if minCuenta:
                        cuentas_info['minCuenta'] = minCuenta

        return cuentas_info
    else:
        raise Exception(f"Error obteniendo cuentas: {response.status_code} - {response.content.decode('utf-8')}")

def ejecutar_transaccion(cuenta_id, monto, token, callback_url, tipo="depositar"):
    soap_body = f"""
    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:PersonService" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
       <soapenv:Header/>
       <soapenv:Body>
          <urn:{tipo}>
             <cuenta_id xsi:type="xsd:int">{cuenta_id}</cuenta_id>
             <monto xsi:type="xsd:float">{monto}</monto>
             <token xsi:type="xsd:string">{token}</token>
             <callback_url xsi:type="xsd:string">{callback_url}</callback_url>
          </urn:{tipo}>
       </soapenv:Body>
    </soapenv:Envelope>
    """
    
    headers = {
        'Content-Type': 'text/xml; charset=utf-8',
        'SOAPAction': f'urn:PersonService#{tipo}'
    }

    response = requests.post(URL, data=soap_body, headers=headers)
    # Print response for debugging
    print(f"Response for {tipo} transaction:")
    print(response.content.decode('utf-8'))

    if response.status_code == 200:
        root = ET.fromstring(response.content)
        # Adjust namespaces
        ns = {
            'soapenv': 'http://schemas.xmlsoap.org/soap/envelope/',
            'ns1': 'urn:PersonService',
            'xsi': 'http://www.w3.org/2001/XMLSchema-instance',
            'xsd': 'http://www.w3.org/2001/XMLSchema',
            'enc': 'http://schemas.xmlsoap.org/soap/encoding/'
        }

        # Check for SOAP Fault
        fault_element = root.find('.//soapenv:Fault', ns)
        if fault_element is not None:
            fault_string = fault_element.find('faultstring').text
            return f"Transacción #{token}, {tipo.capitalize()}, {monto:.2f}, Fallido: {fault_string}"

        # Check for return value
        return_element = root.find('.//ns1:return', ns)
        if return_element is not None:
            return_value = return_element.text
            if return_value == 'true' or return_value == '1':
                return f"Transacción #{token}, {tipo.capitalize()}, {monto:.2f}, Exitoso"
            else:
                return f"Transacción #{token}, {tipo.capitalize()}, {monto:.2f}, Fallido: {return_value}"
        else:
            # No return value, assume success
            return f"Transacción #{token}, {tipo.capitalize()}, {monto:.2f}, Exitoso"
    else:
        # Check if it's a SOAP Fault
        root = ET.fromstring(response.content)
        fault_string_element = root.find('.//faultstring')
        if fault_string_element is not None:
            fault_string = fault_string_element.text
            return f"Transacción #{token}, {tipo.capitalize()}, {monto:.2f}, Fallido: {fault_string}"
        else:
            return f"Transacción #{token}, {tipo.capitalize()}, {monto:.2f}, Fallido: HTTP {response.status_code}"

def simular_concurrencia_indefinida(num_hilos, cuentas_info, output_widget):
    cuentas = [cuenta for cuenta in cuentas_info['cuentas']]
    transaction_counter = 1

    def registrar_transaccion(resultado):
        nonlocal transaction_counter
        # Use root.after to update the GUI from the main thread
        root.after(0, lambda: output_widget.insert(tk.END, f"#{transaction_counter}, {resultado}\n"))
        root.after(0, output_widget.see, tk.END)
        transaction_counter += 1
    
    def ejecutar_hilo():
        while not stop_event.is_set():
            if not cuentas:
                registrar_transaccion("No hay cuentas disponibles para realizar transacciones.")
                break
            cuenta = random.choice(cuentas)
            try:
                cuenta_id = cuenta['id']
                saldo = float(cuenta['saldo'])
            except KeyError:
                registrar_transaccion("Cuenta inválida encontrada, omitiendo.")
                continue

            # Randomly choose deposit or withdraw
            tipo_transaccion = random.choice(["depositar", "retirar"]) if saldo > 0 else "depositar"
            if tipo_transaccion == "retirar":
                monto = min(saldo, random.uniform(10, 1000))
            else:
                monto = random.uniform(10, 1000)

            token = str(uuid.uuid4())
            callback_url = 'http://localhost:8000/colas/cliente/deposito.php'  # Dummy callback URL

            # Result of each transaction
            resultado = ejecutar_transaccion(cuenta_id, monto, token, callback_url, tipo_transaccion)
            registrar_transaccion(resultado)

            time.sleep(random.uniform(1, 3))  # Transaction delay

    # Start the threads
    threads = []
    for _ in range(num_hilos):
        t = Thread(target=ejecutar_hilo)
        t.daemon = True
        t.start()
        threads.append(t)

    # Wait for stop_event to be set
    while not stop_event.is_set():
        time.sleep(0.1)

    # Wait for all threads to finish
    for t in threads:
        t.join()

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
        print("Cuentas Info:", cuentas_info)

        if not cuentas_info['cuentas']:
            output_text.insert(tk.END, "ERROR - No se han encontrado cuentas en el sistema.\n")
            return

        # Run the simulation in a separate thread to keep the GUI responsive
        simulation_thread = Thread(target=run_simulation_in_background, args=(num_hilos, cuentas_info))
        simulation_thread.start()

    except ValueError as ve:
        output_text.insert(tk.END, "ERROR - Ingrese un número válido de hilos (número entero).\n")
    except Exception as e:
        output_text.insert(tk.END, f"ERROR - {str(e)}\n")
        print("Exception:", e)

def parar_simulacion():
    stop_event.set()
    output_text.insert(tk.END, "Simulación detenida.\n")

# Create the GUI with Tkinter
root = tk.Tk()
root.title("Simulación de Transacciones Concurrentes")
root.geometry("600x500")
root.configure(bg="#2c2c2c")  # Dark mode

# Label for the number of threads
label_hilos = tk.Label(root, text="Ingrese la cantidad de hilos:", bg="#2c2c2c", fg="white", font=("Arial", 12))
label_hilos.pack(pady=10)

# Entry for threads
entry_hilos = tk.Entry(root, bg="#1e1e1e", fg="white", font=("Arial", 12))
entry_hilos.pack(pady=10)

# Button to start simulation
start_button = tk.Button(root, text="Iniciar Simulación", command=iniciar_simulacion, bg="#007bff", fg="white", font=("Arial", 12))
start_button.pack(pady=10)

# Button to stop simulation
stop_button = tk.Button(root, text="Parar Simulación", command=parar_simulacion, bg="#dc3545", fg="white", font=("Arial", 12))
stop_button.pack(pady=10)

# Text area to display transaction results
output_text = scrolledtext.ScrolledText(root, bg="#1e1e1e", fg="white", font=("Arial", 10), height=15, width=70)
output_text.pack(pady=10)

root.mainloop()
