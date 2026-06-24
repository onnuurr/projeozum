FROM python:3.12-slim

WORKDIR /app

# PyMuPDF tekerlek olarak gelir; ezdxf saf Python. Ek sistem bağımlılığı gerekmez.
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY converter.py main.py ./

EXPOSE 8200
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8200"]
