# CURLOPT_USERAGENT = > __CLASS__.' HTTP client',
# CURLOPT_URL = > $this->paymentUrl,
# CURLOPT_HEADER = > false,
# CURLOPT_POST = > true,
# CURLOPT_RETURNTRANSFER = > true,
# CURLOPT_SSL_VERIFYHOST = > false,
# CURLOPT_SSL_VERIFYPEER = > true,
# CURLOPT_SSLKEY = > $this->keyDir. $this->merchantID.'.key',
# CURLOPT_SSLCERT = > $this->keyDir. $this->merchantID.'.pem',
# CURLOPT_CAINFO = > $this->keyDir.'chain-ecomm-ca-root-ca.crt',
# CURLOPT_POSTFIELDS = > http_build_query($data),
import pycurl
import json
import urllib.parse
from io import BytesIO
oplata = {
                   'client_ip_addr' :'127.0.0.1',
                   'command':'v',
                   'amount' :  8,
                   'description' : 'asd',
                   'currency' :840,
                   'language' :'ru'}
status = {
                   'client_ip_addr' :'127.0.0.1',
                   'command':'c',
}
cancelTrans = {
    'client_ip_addr': '127.0.0.1',
    'command': 'c',
}
closeDay = {
        'command' : 'b',
}

def sendRequest(data):
 c = pycurl.Curl()
 buffer = BytesIO()
 c.setopt(pycurl.URL, "https://ecomm.hamkorbank.uz:7443/ecomm2/MerchantHandler")
 c.setopt(pycurl.POST,True)
 c.setopt(pycurl.SSL_VERIFYHOST,False)
 c.setopt(pycurl.SSL_VERIFYPEER,True)
 c.setopt(pycurl.SSLKEYPASSWD, 'testabad')
 c.setopt(pycurl.SSLCERT,'imakstore.pem')
 c.setopt(pycurl.CAINFO,'imakstore.pem')
 c.setopt(pycurl.WRITEFUNCTION, buffer.write)
 c.setopt(pycurl.POSTFIELDS,urllib.parse.urlencode(data))
 c.perform()
 htmlString = buffer.getvalue().decode('UTF-8')
 c.close()
 return str(htmlString)
def getRedirectURL(trans_id):
    trans_id = {'trans_id': trans_id, }
    return "https://ecomm.hamkorbank.uz:7443/ecomm2/ClientHandler"+str(urllib.parse.urlencode(trans_id))
def redirect(trans_id):
    c = pycurl.Curl()
    buffer = BytesIO()
    trans_id = {'trans_id': trans_id, }
    c.setopt(pycurl.URL, "https://ecomm.hamkorbank.uz:7443/ecomm2/ClientHandler")
    c.setopt(pycurl.POST, True)
    c.setopt(pycurl.SSL_VERIFYHOST, False)
    c.setopt(pycurl.SSL_VERIFYPEER, True)
    c.setopt(pycurl.SSLKEYPASSWD, 'testabad')
    c.setopt(pycurl.TRANSFERTEXT, True)
    c.setopt(pycurl.SSLCERT, 'imakstore.pem')
    c.setopt(pycurl.CAINFO, 'imakstore.pem')
    c.setopt(pycurl.WRITEFUNCTION, buffer.write)
    c.setopt(pycurl.POSTFIELDS, urllib.parse.urlencode(trans_id))
    c.perform()
    htmlString = buffer.getvalue()
    c.close()
    return htmlString


trans_id = sendRequest(oplata).split(':')[1]
print(trans_id)
print(redirect(trans_id))
status['trans_id']=trans_id
cancelTrans['trans_id']=trans_id
print(sendRequest(status))
#print(sendRequest(cancelTrans))
print(sendRequest(closeDay))
print(getRedirectURL(trans_id))